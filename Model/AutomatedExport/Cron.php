<?php declare(strict_types=1);

namespace DEG\CustomReports\Model\AutomatedExport;

use DEG\CustomReports\Api\AutomatedExportRepositoryInterface;
use DEG\CustomReports\Api\CustomReportRepositoryInterface;
use DEG\CustomReports\Api\DeleteDynamicCronInterface;
use DEG\CustomReports\Block\Adminhtml\Report\Grid;
use DEG\CustomReports\Helper\ConfigHelper;
use DEG\CustomReports\Model\AutomatedExport;
use DEG\CustomReports\Registry\CurrentCustomReport;
use Exception;
use Magento\Cron\Model\Schedule;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;

class Cron
{
    private $automatedExportRepository;
    private $deleteDynamicCron;
    private $resultPageFactory;
    private $currentCustomReportRegistry;
    private $customReportRepository;
    private $configHelper;
    private $logger;


    /**
     * Cron constructor.
     *
     * @param \DEG\CustomReports\Api\AutomatedExportRepositoryInterface $automatedExportRepository
     * @param \DEG\CustomReports\Api\CustomReportRepositoryInterface    $customReportRepository
     * @param \DEG\CustomReports\Api\DeleteDynamicCronInterface         $deleteDynamicCron
     * @param \Magento\Framework\View\Result\PageFactory                $resultPageFactory
     * @param \DEG\CustomReports\Registry\CurrentCustomReport           $currentCustomReportRegistry
     * @param \Psr\Log\LoggerInterface                                  $logger
     */
    public function __construct(
        AutomatedExportRepositoryInterface $automatedExportRepository,
        CustomReportRepositoryInterface $customReportRepository,
        DeleteDynamicCronInterface $deleteDynamicCron,
        PageFactory $resultPageFactory,
        CurrentCustomReport $currentCustomReportRegistry,
        ConfigHelper $configHelper,
        LoggerInterface $logger
    ) {
        $this->automatedExportRepository = $automatedExportRepository;
        $this->deleteDynamicCron = $deleteDynamicCron;
        $this->resultPageFactory = $resultPageFactory;
        $this->currentCustomReportRegistry = $currentCustomReportRegistry;
        $this->customReportRepository = $customReportRepository;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
    }

    /**
     * @param \Magento\Cron\Model\Schedule $schedule
     *
     * @return bool
     */
    public function execute(Schedule $schedule)
    {
        /** @var $reportGrid \DEG\CustomReports\Block\Adminhtml\Report\Grid */
        /** @var $exportBlock \DEG\CustomReports\Block\Adminhtml\Report\Export */

        try {
            $jobCode = $schedule->getJobCode();
            preg_match('/automated_export_(\d+)/', $jobCode, $jobMatch);
            if (!isset($jobMatch[1])) {
                throw new LocalizedException(__('No profile ID found in job_code.'));
            }
            $automatedExportId = $jobMatch[1];

            $automatedExport = $this->automatedExportRepository->getById($automatedExportId);
            if (!$automatedExport->getId()) {
                $this->deleteDynamicCron->execute($jobCode);
                throw new LocalizedException(__('Automated Export ID %1 does not exist.', $automatedExportId));
            }

            $this->runAutomatedExport($automatedExport);


        } catch (Exception $e) {
            $this->logger->critical('Cronjob exception for job_code '.$jobCode.': '.$e->getMessage());
        }

        return true;
    }

    /**
     * @param AutomatedExport $automatedExport
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function runAutomatedExport(AutomatedExport $automatedExport)
    {
        $customReportIds = $automatedExport->getCustomreportIds();

        foreach ($customReportIds as $customReportId) {
            $customReport = $this->customReportRepository->getById($customReportId);
            $this->currentCustomReportRegistry->set($customReport);
            $resultPage = $this->resultPageFactory->create();
            $reportGrid = $resultPage->getLayout()->createBlock(Grid::class, 'report.grid');
            $exportBlock = $reportGrid->getChildBlock('grid.export');
            foreach ($automatedExport->getExportTypes() as $exportType) {
                //@todo: Extract exporter logic to its own class
                if ($exportType == 'local_file_drop') {
                    foreach ($automatedExport->getFileTypes() as $fileType) {
                        if ($fileType == 'csv') {
                            $response = $exportBlock->getCronCsvFile($customReport, $automatedExport);
                            if (isset($response['value'])) {
                                $this->logger->info(__('Successfully exported var/%1 file', $response['value']));
                            }

                            if ($automatedExport->getEmailRecipients()) {
                                $this->sendAutomatedReportFile($automatedExport, $response['value']);
                            }
                        }
                    }
                }
            }
        }
    }


    /**
     * Send report file to recipients
     *
     * @param $automatedExport
     * @param $file
     * @return void
     */
    protected function sendAutomatedReportFile($automatedExport, $file)
    {
        $recipients = explode(',', $automatedExport->getEmailRecipients());

        if (count($recipients) > 0) {

            $now = new \DateTime();

            $mail = new \Zend_Mail();
            $mail->setType(\Zend_Mime::MULTIPART_RELATED);

            foreach($recipients as $recipient){
                $mail->addTo($recipient);
            }

            $mail->setFrom($this->configHelper->getEmailFrom());
            $mail->setSubject($automatedExport->getTitle() . ' - ' . $now->format("Y-m-d"));
            $mail->setBodyText("Report Attached - " . $automatedExport->getTitle());
            $attachmentFile = $mail->createAttachment(file_get_contents($this->configHelper->getRootPath() . '/var/' . $file));
            $attachmentFile->type = 'text/csv';
            $attachmentFile->disposition = \Zend_Mime::DISPOSITION_INLINE;
            $attachmentFile->encoding = \Zend_Mime::ENCODING_BASE64;
            $attachmentFile->filename = basename($file);

            try
            {
                $mail->send();
            }
            catch(\Exception $e)
            {
                $this->logger->error('AUTOMATED EXPORT EMAIL: Unable to send email - ' . $e->getMessage());
            }

        }
    }
}
