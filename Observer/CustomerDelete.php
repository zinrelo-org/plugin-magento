<?php

namespace Zinrelo\LoyaltyRewards\Observer;

use Magento\Framework\Event\Observer;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Zinrelo\LoyaltyRewards\Helper\Data;

class CustomerDelete implements ObserverInterface
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * Customer Delete constructor.
     *
     * @param Data $helper
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        Data $helper,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->customerRepository = $customerRepository;
        $this->helper = $helper;
    }

    /**
     * Delete customer event to zinrelo
     *
     * @param Observer $observer
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $event = $this->helper->getRewardEvents();
        if (in_array('customer_delete', $event)) {
            $customerId = $observer->getEvent()->getCustomer()->getEntityId();
            $customer = $this->customerRepository->getById($customerId);
            $customerData = $customer->__toArray();
            $params = [
                "member_id" => $customer->getEmail(),
                "activity_id" => "customer_delete",
                "data" => $customerData
            ];
            $url = $this->helper->getWebHookUrl();
            $params = $this->helper->json->serialize($params);
            $this->helper->request($url, $params, "post");
            return true;
        }
    }
}
