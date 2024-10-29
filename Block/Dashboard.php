<?php

namespace Zinrelo\LoyaltyRewards\Block;

use Magento\Framework\View\Element\Template;
use Zinrelo\LoyaltyRewards\Helper\Config;

class Dashboard extends Template
{
    /**
     * @var Config
     */
    private $config;

    /**
     * Dashboard constructor.
     *
     * @param Template\Context $context
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
    }

    /**
     * Is Module Enabled
     *
     * @return bool
     */
    public function isModuleEnabled()
    {
        return $this->config->isModuleEnabled();
    }

    /**
     * Get Partner Id
     *
     * @return mixed|string
     */
    public function getPartnerId()
    {
        return $this->config->getPartnerId();
    }
}
