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
        $existingWebhookUrl =  $this->helper->getWebHookUrl();
        $this->logger->info('Existing Webhook URL: ' . $existingWebhookUrl);
        if (empty($existingWebhookUrl)) {
            $url = $this->helper->getWebHookIntegrationURL();
            $WebhookData = $this->helper->createOrUpdateZIFIntegration($url);
            $WebhookUrl = $WebhookData['data']['config']['zif_config']['workflow_url'];
            $WebhookIntegrationID = $WebhookData['data']['id'];
            $this->logger->info('New Webhook URL: ' . $WebhookUrl);
            if ($WebhookUrl) {
                $this->helper->saveWebHookUrl($WebhookUrl);
                $this->helper->saveWebHookIntegrationID($WebhookIntegrationID);
            }
        }
        else{
            $webhookIntegrationID =  $this->helper->getWebHookIntegrationID();
            if (!empty($webhookIntegrationID)){
                $url = $this->helper->getWebHookIntegrationURL() . "/" . $webhookIntegrationID;
                $newWebhookData = $this->helper->createOrUpdateZIFIntegration($url);
            }
        }
    }
}