<?php

namespace DEG\CustomReports\Console\Command;


use DEG\CustomReports\Api\AutomatedExportRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *
 */
class AutomatedExport extends Command
{
    const COMMAND_NAME = 'automatedexport:run';

    protected $cron;
    protected $logger;
    protected $appState;
    protected $automatedExportRepository;

    public function __construct(
        \Magento\Framework\App\State $state,
        \Psr\Log\LoggerInterface $logger,
        AutomatedExportRepositoryInterface $automatedExportRepository,
        \DEG\CustomReports\Model\AutomatedExport\Cron $cron
    ) {
        $this->appState = $state;
        $this->logger   = $logger;
        $this->cron = $cron;
        $this->automatedExportRepository = $automatedExportRepository;

        parent::__construct(self::COMMAND_NAME);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_CRONTAB);
        
        $automatedExportId = $input->getArgument('exportId');

        $automatedExport = $this->automatedExportRepository->getById($automatedExportId);

        if ($automatedExport) {

            $output->writeln("Running Export - " . $automatedExportId);
            $this->cron->runAutomatedExport($automatedExport);
            $output->writeln("Finished Export - " . $automatedExportId);

        } else {
            $output->writeln("Can't load export id - " . $automatedExportId);
        }
    }


    /**
     * @return $this|void
     */
    protected function configure()
    {
        $this->setName(self::COMMAND_NAME)
            ->addArgument('exportId', InputArgument::REQUIRED)
            ->setDescription("Run Low Stock Reports");

        return $this;
    }
}
