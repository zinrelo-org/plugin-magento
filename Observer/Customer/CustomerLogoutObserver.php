<?php
namespace Zinrelo\LoyaltyRewards\Observer\Customer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Zinrelo\LoyaltyRewards\Helper\Data;

class CustomerLogoutObserver implements ObserverInterface
{

    /**
     * @var Data
     */
    private $helper;

    /**
     * CustomerLogoutObserver constructor.
     *
     * @param Data $helper
     */
    public function __construct(
        Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Execute
     *
     * @param Observer $observer
     */
    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
        $this->helper->deleteCookie();
    }
}
