<?php

namespace Zinrelo\LoyaltyRewards\Block\Adminhtml\Sales\Order;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Zinrelo\LoyaltyRewards\Helper\Data;

class ZinreloInvoiceViewDiscount extends Template
{

    /**
     * @var Data
     */
    private $helper;
    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     * Zinrelo Invoice View Discount constructor.
     *
     * @param Context $context
     * @param Data $helper
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $helper,
        InvoiceRepositoryInterface $invoiceRepository,
        array $data = []
    ) {
        $this->helper = $helper;
        $this->invoiceRepository = $invoiceRepository;
        parent::__construct(
            $context,
            $data
        );
    }

    /**
     * This function call initTotals
     *
     * @return ZinreloInvoiceViewDiscount
     */
    public function initTotals()
    {
        $invoiceId = $this->getRequest()->getParam('invoice_id');
        $invoiceData = $this->invoiceRepository->get($invoiceId);
        $orderId = $invoiceData->getOrderId();
        $totalAmount = $this->helper->getRedeemRewardDiscountData($orderId);
        if ($totalAmount["status"]) {
            $this->getParentBlock()->addTotal(
                new DataObject(
                    [
                        'code' => 'zinrelo_discount',
                        'strong' => $this->getStrong(),
                        'value' => $totalAmount["value"],
                        'base_value' => $totalAmount["value"],
                        'label' => __($totalAmount["label"]),
                    ]
                ),
                $this->getAfter()
            );
        }
        return $this;
    }

    /**
     * This function call Order
     *
     * @return mixed
     */
    public function getOrder()
    {
        return $this->getParentBlock()->getOrder();
    }
}
