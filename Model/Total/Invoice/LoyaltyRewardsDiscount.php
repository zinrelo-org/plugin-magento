<?php

namespace Zinrelo\LoyaltyRewards\Model\Total\Invoice;

use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;
use Zinrelo\LoyaltyRewards\Helper\Reward;

class LoyaltyRewardsDiscount extends AbstractTotal
{
    /**
     * @var Reward
     */
    private $helper;

    /**
     * LoyaltyRewardsDiscount constructor.
     *
     * @param Reward $helper
     */
    public function __construct(
        Reward $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Invoice show discount show in order view page
     *
     * @param Invoice $invoice
     * @return Data
     */
    public function collect(Invoice $invoice)
    {
        $orderId = $invoice->getOrderId();
        return $this->helper->getCollectRewardValueData($orderId, 'invoice', $invoice);
    }
}
