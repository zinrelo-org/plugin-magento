<?php

namespace Zinrelo\LoyaltyRewards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Zinrelo\LoyaltyRewards\Helper\Data;
use Zinrelo\LoyaltyRewards\Logger\Logger as ZinreloLogger;

class ConfigSaveObserver implements ObserverInterface
{
    /**
     * @var Data
     */
    private $helper;
    /**
     * @var ZinreloLogger
     */
    private $logger;
    /**
     * Data constructor.
     *
     * @param Data $helper
     * @param ZinreloLogger $logger
     */
    public function __construct(
        Data $helper,
        ZinreloLogger $logger,
    ) {
        $this->helper = $helper;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        $webhookUrl =  $this->helper->getWebHookUrl();
        $this->logger->info('Webhook URL: ' . $webhookUrl);
        if (empty($webhookUrl)) {
            $newWebhookUrl = $this->helper->createWebHookUrl();
            $this->logger->info('New Webhook URL: ' . $newWebhookUrl);
            if ($newWebhookUrl) {
                $this->helper->saveWebHookUrl($newWebhookUrl);
            }
        }
    }
}