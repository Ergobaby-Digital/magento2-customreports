<?php

namespace DEG\CustomReports\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

/**
 * Class ConfigHelper
 *
 * @package DEG\CustomReports\Helper
 */
Class ConfigHelper extends AbstractHelper
{

    // System config paths
    const EMAIL_SENDER_CONFIG_PATH          = 'trans_email/ident_general/email';

    /** @var \Magento\Store\Model\StoreManagerInterface  */
    protected $storeManager;

    /** @var \Magento\Framework\Filesystem\DirectoryList */
    protected $directoryList;


    /**
     * ConfigHelper constructor.
     *
     * @param Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(Context $context,
                                \Magento\Framework\Filesystem\DirectoryList $directoryList,
                                \Magento\Store\Model\StoreManagerInterface $storeManager)
    {
        $this->storeManager = $storeManager;
        $this->directoryList = $directoryList;
        parent::__construct($context);
    }


    /**
     * Get email sender
     *
     * @return string
     */
    public function getEmailFrom() : string
    {
        return (string) $this->scopeConfig->getValue(self::EMAIL_SENDER_CONFIG_PATH);
    }

    public function getRootPath()
    {
        return $this->directoryList->getRoot();
    }

}
