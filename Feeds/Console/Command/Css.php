<?php
namespace Aks\Feeds\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Aks\Feeds\Model\FeedData;
use Psr\Log\LoggerInterface;
 
class Css extends Command
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
        $this ->setName('aks:generatefeed:css')
              ->setDescription('Generates xml feed for separate type of campaign - CSS.')
              ->setDefinition([]);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->feedData->generateFeedCss();
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
    }
}
