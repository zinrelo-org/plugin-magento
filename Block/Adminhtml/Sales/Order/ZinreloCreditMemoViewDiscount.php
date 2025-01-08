<?php

namespace Zinrelo\LoyaltyRewards\Block\Adminhtml\Sales\Order;

use Magento\Framework\DataObject;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Zinrelo\LoyaltyRewards\Helper\Data;
use Magento\Sales\Api\CreditmemoRepositoryInterface;

class ZinreloCreditMemoViewDiscount extends Template
{
    /**
     * @var Data
     */
    private $helper;
    /**
     * @var CreditmemoRepositoryInterface
     */
    private $creditMemoRepository;

    /**
     * Zinrelo Credit Memo View Discount constructor.
     *
     * @param Context $context
     * @param Data $helper
     * @param CreditmemoRepositoryInterface $creditedRepository
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $helper,
        CreditmemoRepositoryInterface $creditedRepository,
        array $data = []
    ) {
        $this->helper = $helper;
        $this->creditMemoRepository = $creditedRepository;
        parent::__construct(
            $context,
            $data
        );
    }

    /**
     * This function call initTotals
     *
     * @return ZinreloCreditMemoViewDiscount
     */
    public function initTotals()
    {
        $creditMemoId = $this->getRequest()->getParam('creditmemo_id');
        $creditedData = $this->creditMemoRepository->get($creditMemoId);
        $totalAmount = $this->helper->getRedeemRewardDiscountData($creditedData->getOrderId());
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
