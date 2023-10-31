<?php
namespace Aks\Feeds\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Catalog\Helper\Image as ProductImage;
use Psr\Log\LoggerInterface;
use Aks\Feeds\Helper\Data as Helper;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\State;
use Magento\Catalog\Model\CategoryRepository;
use Magento\Framework\App\ResourceConnection;

class FeedData
{
    const GOOGLE_FEED_XML = 'feed_google_merchant.xml';
    const FACEBOOK_FEED_XML = 'feed_facebook.xml';
    const PERFORMANT_FEED_CSV = 'feed_2performant.csv';
    const CSS_FEED_XML = 'css_feed.xml';

    private $logger;
    private $productRepository;
    private $stockRegistry;
    private $searchCriteria;
    private $productImage;
    private $helper;
    private $categoriesPaths;
    private $storeManager;
    private $state;
    private $categoryRepository;

    public function __construct (
        LoggerInterface $logger,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $criteria,
        ProductImage $productImage,
        StockRegistryInterface $stockRegistry,
        Helper $helper,
        StoreManagerInterface $storeManager,
        State $state,
        ResourceConnection $resourceConnection,
        CategoryRepository $categoryRepository
    )
    {
        $this->logger = $logger;
        $this->productRepository = $productRepository;
        $this->searchCriteria = $criteria;
        $this->productImage = $productImage;
        $this->stockRegistry = $stockRegistry;
        $this->helper = $helper;
        $this->storeManager = $storeManager;
        $this->categoryRepository = $categoryRepository;
        $this->connection = $resourceConnection->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $this->categoriesPaths = $helper->getCategoriesPaths();
        //$this->state = $state->setAreaCode(\Magento\Framework\App\Area::AREA_CRONTAB);
    }


    public function generateFeedGoogleMerchant()
    {
        $this->logger->info('Generating Google Merchant feed...');

        $filter = $this->searchCriteria
                        ->addFilter('status', 1, 'eq')
                        ->create();
        $productItems = $this->productRepository->getList($filter)->getItems();
        $vals=array('&amp;','nbsp;','lt;','gt;','icirc;');
        $replace=array('','','','','i');

        $output = '<?xml version="1.0"?>
        <rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
          <channel>
            <title>{Store}</title>
            <link>https://www.{Store}.com</link>
            <description>Feed {Store}</description>
        ';

        try{
            foreach($productItems as $key=>$product)
            {
                if($product->getVisibility()!=1)
                {
                    //if(!$product->getIsAutor())
                    //{
                        $categoryPaths = [];
                        foreach($product->getCategoryIds() as $categoryId){
                            if(isset($this->categoriesPaths[$categoryId])) {
                                $categoryPaths[] = $this->categoriesPaths[$categoryId];
                            }
                        }
                    
                        $category = count($categoryPaths)? implode(" > ", $categoryPaths) : "";
                        $categoryComma = count($categoryPaths)? implode(" , ", $categoryPaths) : "";
                        $category =  str_replace($vals, $replace, htmlspecialchars(strip_tags($category)));
                        $categoryComma = str_replace($vals, $replace, htmlspecialchars(strip_tags($categoryComma)));

                        if($product->getDescription() != "") {   
                            $description = $product->getDescription();
                        } else if($product->getShortDescription() != "")
                        {
                            $description = $product->getShortDescription();
                        } 
                        else {
                            $description = htmlspecialchars($product->getName()).' - '.$product->getSku(); 
                        }  
                        $description = $this->helper->stripInvalidXml(strip_tags(html_entity_decode($description)));

                        $quantity = $this->stockRegistry->getStockItemBySku($product->getSku())->getQty();
                        $stockItem = $this->stockRegistry->getStockItemBySku($product->getSku());
                        
                        if ($stockItem->getIsInStock() &&  $quantity > 0) $availability = 'in stock';
                        else $availability = 'out of stock';
                        
                        $_finalPrice = $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
                        $_regularPrice = $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();

                        if($_finalPrice && ($_finalPrice!= $_regularPrice)) $price = $_finalPrice;
                        else  $price = $_regularPrice;
                        /*$author = "";
                        $author = $product->getResource()->getAttribute('autori')->getFrontend()->getValue($product);
                        if($author == "" || empty($author) || $author == "<p>"){
                            continue;
                        }
                        $author = str_replace($vals, $replace, htmlspecialchars(strip_tags($author)));
                        $colectie = "";
                        $colectie = $product->getResource()->getAttribute('colectie')->getFrontend()->getValue($product);
                        if($colectie == "" || empty($colectie) || $colectie == "<p>"){
                            continue;
                        }

                        $colectie = str_replace($vals, $replace, htmlspecialchars(strip_tags($colectie)));*/
                        $image = $this ->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA ).'catalog/product'.$product->getImage();

                        $output .= '<item>
                        <g:id>'.$product->getSku().'</g:id>
                        <g:title><![CDATA['.$product->getName().']]></g:title>
                        <g:description><![CDATA['.$description.']]></g:description>
                        <g:link>'.$product->getProductUrl().'</g:link>
                        <g:image_link><![CDATA['.$image.']]></g:image_link>
                        <g:condition>new</g:condition>
                        <g:availability>'.$availability.'</g:availability>
                        <g:sale_price>'.$price.' '.$this->getCurrentCurrencySymbol().'</g:sale_price>
                        <g:price>'.$_regularPrice.' '.$this->getCurrentCurrencySymbol().'</g:price>
                        <g:brand>{Store}</g:brand>
                        <g:mpn><![CDATA['.$product->getSku().']]></g:mpn>
                        <g:google_product_category>Business & Industrial > Medical > Medical Equipment</g:google_product_category>
                        <g:product_type>'. $category .'</g:product_type>
                        <g:custom_label_0>'.$categoryComma.'</g:custom_label_0>
                        </item>';
                        
                    //}
                }
            }
            $output .= '</channel>
            </rss>';
          
        }catch (\Exception $e) {
            die($e->getMessage());
        }

    
        $this->helper->saveXMLFile($output, self::GOOGLE_FEED_XML);
        $this->logger->info('Google Merchant feed generated!');
    }

    public function generateFeedCss()
    {
        $this->logger->info('Generating Css Merchant feed...');

        $filter = $this->searchCriteria
                        ->addFilter('status', 1, 'eq')
                        ->create();
        $productItems = $this->productRepository->getList($filter)->getItems();
        $vals=array('&amp;','nbsp;','lt;','gt;','icirc;');
        $replace=array('','','','','i');

        $output = '<?xml version="1.0"?>
        <rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
          <channel>
            <title>{Store}</title>
            <link>https://www.{Store}.com</link>
            <description>Feed {Store}</description>
        ';

        try 
        {
            foreach($productItems as $key=>$product)
            {
                if($product->getVisibility()!=1){

                    //if(!$product->getIsAutor()){

                        if($product->getDescription() != "") {   
                            $description = $product->getDescription();
                        } else if($product->getShortDescription() != "")
                        {
                            $description = $product->getShortDescription();
                        } 
                        else {
                            $description = htmlspecialchars($product->getName()).' - '.$product->getSku(); 
                        }  
                        $description = $this->helper->stripInvalidXml(strip_tags(html_entity_decode($description)));

                        $quantity = $this->stockRegistry->getStockItemBySku($product->getSku())->getQty();
                        $stockItem = $this->stockRegistry->getStockItemBySku($product->getSku());
                        
                        if ($stockItem->getIsInStock() &&  $quantity > 0) $availability = 'in stock';
                        else $availability = 'out of stock';
                        
                        $_finalPrice = $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
                        $_regularPrice = $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
                        if($_finalPrice && ($_finalPrice!= $_regularPrice)) $price = $_finalPrice;
                        else  $price = $_regularPrice;

                        $image = $this ->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA ).'catalog/product'.$product->getImage();

                        /*$author = "";
                        $author = $product->getResource()->getAttribute('autori')->getFrontend()->getValue($product);
                        if($author == "" || empty($author) || $author == "<p>"){
                            continue;
                        }
                        $author = str_replace($vals, $replace, htmlspecialchars(strip_tags($author)));*/

                        $categoryPaths = [];
                        foreach($product->getCategoryIds() as $categoryId){
                            if(isset($this->categoriesPaths[$categoryId])) {
                                $categoryPaths[] = $this->categoriesPaths[$categoryId];
                            }
                        }
                        $categoryComma = count($categoryPaths)? str_replace(">","/",$categoryPaths[count($categoryPaths)-1]): "";
                        $categoryComma = str_replace($vals, $replace, htmlspecialchars(strip_tags($categoryComma)));
                       

                        /*$event = '';
                        if($_regularPrice!=$price)
                            $event = '<g:custom_label_3>IS-EVENT</g:custom_label_3>';*/

                        $popularitate = 'LOW';
                        $countPopularitate = $this->selectBestsellerProducts($product->getId());
                        $countPopularitate = count($countPopularitate)?$countPopularitate[0]["rating_pos"] :"1";

                        switch(true){
                            case $countPopularitate>7:
                            $popularitate='BESTSELLER';
                            break;
                            case $countPopularitate>5:
                            $popularitate='HIGH';
                            break;
                            case $countPopularitate>3:
                            $popularitate='MEDIUM';
                            break;
                            case $countPopularitate>1:
                            $popularitate='LOW';
                            break;
                        }

                        $output .= '<item>
                        <g:id>'.$product->getSku().'</g:id>
                        <title><![CDATA['.$product->getName().']]></title>
                        <description><![CDATA['.$description.']]></description>
                        <g:product_type>'.$categoryComma.'</g:product_type>
                        <link>'.$product->getProductUrl().'</link>
                        <g:image_link><![CDATA['.$image.']]></g:image_link>
                        <g:condition>new</g:condition>
                        <g:availability>'.$availability.'</g:availability>
                        <g:price>'.$_regularPrice.' '.$this->getCurrentCurrencySymbol().'</g:price>
                        <g:sale_price>'.$price.' '.$this->getCurrentCurrencySymbol().'</g:sale_price>
                        <g:age_group>'.$categoryComma.'</g:age_group>
                        <g:brand>{Store}</g:brand>
                        </item>';
                    //}
                }
                  
            }
            $output .= '</channel>
            </rss>';
          
        }catch (\Exception $e) {
            die($e->getMessage());
        }

    
        $this->helper->saveXMLFile($output, self::CSS_FEED_XML);
        $this->logger->info('Css feed generated!');
    }

    public function generateFeedFacebook()
    {
        $this->logger->info('Generating Facebook feed...');

        $filter = $this->searchCriteria
                        ->addFilter('status', 1, 'eq')
                        ->create();
        $productItems = $this->productRepository->getList($filter)->getItems();
        $vals=array('&amp;','nbsp;','lt;','gt;','icirc;','&');
        $replace=array('','','','','i','');

        $output = '<?xml version="1.0"?>
        <rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">
        <channel>
            <title>{Store}</title>
            <link>https://www.{Store}.com</link>
            <description>Feed {Store}</description>
        ';

        try{
            foreach($productItems as $key=>$product)
            {
                if($product->getVisibility()!=1)
                {
                    //if(!$product->getIsAutor())
                    //{
                        $categoryPaths = [];
                        foreach($product->getCategoryIds() as $categoryId){
                            if(isset($this->categoriesPaths[$categoryId])) {
                                $categoryPaths[] = $this->categoriesPaths[$categoryId];
                            }
                        }
                    
                        $category = count($categoryPaths)? implode(" > ", $categoryPaths) : "";
                        $categoryComma = count($categoryPaths)? implode(" , ", $categoryPaths) : "";
                        $category =  str_replace($vals, $replace, htmlspecialchars(strip_tags($category)));
                        $categoryComma = str_replace($vals, $replace, htmlspecialchars(strip_tags($categoryComma)));

                        if($product->getDescription() != "") {   
                            $description = $product->getDescription();
                        } else if($product->getShortDescription() != "")
                        {
                            $description = $product->getShortDescription();
                        } 
                        else {
                            $description = htmlspecialchars($product->getName()).' - '.$product->getSku(); 
                        }  
                        $description = $this->helper->stripInvalidXml(strip_tags(html_entity_decode($description)));

                        $quantity = $this->stockRegistry->getStockItemBySku($product->getSku())->getQty();
                        $stockItem = $this->stockRegistry->getStockItemBySku($product->getSku());
                        
                        if ($stockItem->getIsInStock() &&  $quantity > 0) $availability = 'in stock';
                        else $availability = 'out of stock';
                        
                        $_finalPrice = $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
                        $_regularPrice = $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();

                        if($_finalPrice && ($_finalPrice!= $_regularPrice)) $price = $_finalPrice;
                        else  $price = $_regularPrice;
                        /*$author = "";
                        $author = $product->getResource()->getAttribute('autori')->getFrontend()->getValue($product);
                        if($author == "" || empty($author) || $author == "<p>"){
                            continue;
                        }
                        $author = str_replace($vals, $replace, htmlspecialchars(strip_tags($author)));
                        $colectie = "";
                        $colectie = $product->getResource()->getAttribute('colectie')->getFrontend()->getValue($product);
                        if($colectie == "" || empty($colectie) || $colectie == "<p>"){
                            continue;
                        }

                        $colectie = str_replace($vals, $replace, htmlspecialchars(strip_tags($colectie)));*/
                        $image = $this ->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA ).'catalog/product'.$product->getImage();

                        $output .= '<item>
                        <g:id>'.$product->getSku().'</g:id>
                        <g:title><![CDATA['.$product->getName().']]></g:title>
                        <g:description><![CDATA['.$description.']]></g:description>
                        <g:link>'.$product->getProductUrl().'</g:link>
                        <g:image_link><![CDATA['.$image.']]></g:image_link>
                        <g:condition>new</g:condition>
                        <g:availability>'.$availability.'</g:availability>
                        <g:sale_price>'.$price.' '.$this->getCurrentCurrencySymbol().'</g:sale_price>
                        <g:price>'.$_regularPrice.' '.$this->getCurrentCurrencySymbol().'</g:price>
                        <g:brand>{Store}</g:brand>
                        <g:mpn><![CDATA['.$product->getSku().']]></g:mpn>
                        <g:google_product_category>Business & Industrial > Medical > Medical Equipment</g:google_product_category>
                        <g:product_type>'. $category .'</g:product_type>
                        <g:custom_label_0>'.$categoryComma.'</g:custom_label_0>
                        </item>';
                        
                    //}
                }
            }
            $output .= '</channel>
            </rss>';
          
        }catch (\Exception $e) {
            die($e->getMessage());
        }
        $this->helper->saveXMLFile($output, self::FACEBOOK_FEED_XML);
        $this->logger->info('Facebook feed generated!');
    }

    public function generateFeed2Performant()
    {
        $this->logger->info('Generating 2performant feed...');

        $filter = $this->searchCriteria
                        ->addFilter('status', 1, 'eq')
                        ->create();
        $productItems = $this->productRepository->getList($filter)->getItems();
        $vals=array('&amp;','nbsp;','lt;','gt;','icirc;');
        $replace=array('','','','','i');
        $productsData = [];
        $data = [];

        try {
            foreach($productItems as $key=>$product){
                if($product->getVisibility()!=1){
                    $description = "";
                    $short_description = "";
        
                    if($product->getDescription() != "") {   
                        $description = $product->getDescription();
                    } 
                    if($product->getShortDescription() != "")
                    {
                        $short_description = $product->getShortDescription();
                    } 
        
                    $categoryName = "";
                    $subcategoryName = "";
                    $categoryId = $product->getCategoryIds();
            
                    if(count($categoryId)>=2) {
                        $category = $this->categoryRepository->get($categoryId[1], $this->storeManager->getStore()->getId());
                        $categoryName = $category->getName();
                        if(count($categoryId)>=3) {
                            $subcategory = $this->categoryRepository->get($categoryId[2], $this->storeManager->getStore()->getId());
                            $subcategoryName = $subcategory->getName();
                        }
                    }
        
                    $quantity = $this->stockRegistry->getStockItemBySku($product->getSku())->getQty();
                    $stockItem = $this->stockRegistry->getStockItemBySku($product->getSku());

                    if ($stockItem->getIsInStock() &&  $quantity > 0) $stock= '1';
                    else $stock = '0';

                    $_finalPrice = $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
                    $productPrice = $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
  		
                    if($_finalPrice && ($_finalPrice!= $productPrice))  $productPrice .='/'. $_finalPrice;
         
                    $data[$key][0] = substr(htmlspecialchars($product->getName()),0,254);
                    $data[$key][1] = str_replace($vals, $replace, htmlspecialchars(strip_tags($product->getDescription())));
                    $data[$key][2] = str_replace($vals, $replace, htmlspecialchars(strip_tags($short_description)));
                    $data[$key][3] =  $productPrice;
                    $data[$key][4] = $categoryName;
                    $data[$key][5] = $subcategoryName;
                    $data[$key][6] = $product->getProductUrl();
                    $data[$key][7] = $this ->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA ).'catalog/product'.$product->getImage();      
                    $data[$key][8] = $product->getId();
                    $data[$key][9] = '0';        
                    $data[$key][10]= '{Store}';        
                    $data[$key][11]= $stock;    
                    $data[$key][12]= "";  
                }
            }
            $headers=[
                '0'=> [
                    '0' =>  'Title',
                    '1' =>  'Description',
                    '2' =>  'Short message',
                    '3' =>  'Price',
                    '4' =>  'Category',
                    '5' =>  'Subcategory',
                    '6' =>  'URL',
                    '7' =>  'Image URL',
                    '8' =>  'Product ID',
                    '9' =>  'Generate link text',
                    '10'=>  'Brand',
                    '11'=>  'Active',
                    '12'=>  'Other data'
                ]
            ];
        }catch (\Exception $e) {
            die($e->getMessage());
        }

        $data = array_merge($headers, $data);
    
        $this->helper->saveCsvFile($data, self::PERFORMANT_FEED_CSV);
        $this->logger->info('2performant feed generated!');
    }

    public function getCurrentCurrencySymbol()
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
    }

    private function selectBestsellerProducts($productId)
    {
        $select = "SELECT rating_pos from sales_bestsellers_aggregated_monthly where store_id=0 and product_id=$productId";
        return $this->connection->query($select)->fetchAll();
    }
}
