<?php
namespace Aks\Feeds\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Aks\Feeds\Model\FeedData;
use Psr\Log\LoggerInterface;
 
class Facebook extends Command
{
    private $logger;
    private $feedData;

    public function __construct(
        LoggerInterface $logger,
        FeedData $feedData
    ) {
        $this->logger = $logger;
        $this->feedData = $feedData;

        parent::__construct();
    }

    protected function configure()
    {
        $this ->setName('aks:generatefeed:facebook-googleshopping')
              ->setDescription('Generates csv feed for Facebook.')
              ->setDefinition([]);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->feedData->generateFeedFacebook();
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
    }
}
