<?php
namespace Aks\Feeds\Helper;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\File\Csv;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Catalog\Api\CategoryListInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Filesystem;

class Data extends AbstractHelper
{
    private $directoryList;
    private $csvProcessor;
    private $searchCriteriaBuilder;
    private $categoryListInterface;
    private $fileFactory;
    private $categoryRepository;
    private $storeManager;
    private $filesystem;

    public function __construct(
        Context $context,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        CategoryListInterface $categoryListInterface,
        Csv $csvProcessor,
        DirectoryList $directoryList,
        FileFactory $fileFactory,
        CategoryRepositoryInterface $categoryRepository,
        StoreManagerInterface $storeManager,
        Filesystem $filesystem,
        \Magento\Catalog\Helper\Data $catalogData
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->categoryListInterface = $categoryListInterface;
        $this->catalogData = $catalogData;
        $this->csvProcessor = $csvProcessor;
        $this->directoryList = $directoryList;
        $this->fileFactory = $fileFactory;
        $this->categoryRepository = $categoryRepository;
        $this->storeManager = $storeManager;
        $this->filesystem = $filesystem;

        parent::__construct($context);
    }

    public function saveCsvFile($data, $fileName)
    {
        $mediapath = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
        $filePath = $mediapath.'feeds/'.$fileName;
        $this->csvProcessor
    	    ->setDelimiter(',')
        	->setEnclosure('"')
        	->saveData(
            	$filePath,
            	$data
            );
        $this->fileFactory->create(
            $fileName,
            [
                'type' => "filename",
                'value' => $filePath,
                'rm' => true,
            ],
            \Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR,
            'application/octet-stream'
        );
    }

    public function saveXMLFile($data, $fileName)
    {
        $mediapath = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath();
        $filePath = $mediapath.'feeds/'.$fileName;
        file_put_contents($filePath,$data);
    }

    public function getBreadcrumbPath() {
        return $this->catalogData->getBreadcrumbPath();
    }
    
    public function getCategoriesPaths() {
        $categoryPathById = $categoryNameById = $categoryPathNamesById = [];
        $searchCriteria = $this->searchCriteriaBuilder->addFilter('entity_id', 2, 'gt')->create();
        $categories = $this->categoryListInterface->getList($searchCriteria)->getItems();

        foreach ($categories as $category) {
            $categoryPathById[$category->getId()] = $category->getPath();
            $categoryNameById[$category->getId()] = $category->getName();
        }

        foreach ($categoryPathById as $categoryId => $categoryPathIds) {
            $categorySegments = [];
            foreach (explode("/", $categoryPathIds) as $categorySegmentId) {
                if($categorySegmentId != '1' && $categorySegmentId != '2'){
                    $categorySegments[] = $categoryNameById[$categorySegmentId];
                }
            }
            if (count($categorySegments)) {
                $categoryPathNamesById[$categoryId] = implode(" > ", $categorySegments);
            }
        }

        return $categoryPathNamesById;
    }

    public function getCategoryNameById($categoryId) {
        return $this->categoryRepository->get($categoryId,  $this->storeManager->getStore()->getId())->getName();
    }

    public function getSalePrice($product)
    {
        $price = $product->getPrice();

        if($product->getSpecialPrice() > 0) {
            $price = $product->getSpecialPrice();
        }

        return $price.' '.$this->getCurrentCurrencySymbol();
    }

    public function getCurrentCurrencySymbol()
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
    }

    public function stripInvalidXml($value)
    {
        $ret = "";
        $current;
        if (empty($value)) 
        {
            return $ret;
        }
    
        $length = strlen($value);
        for ($i=0; $i < $length; $i++)
        {
            $current = ord($value[$i]);
            if (($current == 0x9) ||
                ($current == 0xA) ||
                ($current == 0xD) ||
                (($current >= 0x20) && ($current <= 0xD7FF)) ||
                (($current >= 0xE000) && ($current <= 0xFFFD)) ||
                (($current >= 0x10000) && ($current <= 0x10FFFF)))
            {
                $ret .= chr($current);
            }
            else
            {
                $ret .= " ";
            }
        }
        return $ret;
    }
    
}