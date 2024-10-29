<?php

namespace Zinrelo\LoyaltyRewards\Model\Total\Quote;

use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Zinrelo\LoyaltyRewards\Helper\Data;

class LoyaltyRewardsDiscount extends AbstractTotal
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * LoyaltyRewardsDiscount constructor.
     *
     * @param Data $helper
     */
    public function __construct(
        Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Quote show discount show in order view page
     *
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return $this|LoyaltyRewardsDiscount
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        parent::collect($quote, $shippingAssignment, $total);
        $rewardData = $this->helper->getRewardRulesData($quote);
        if (isset($rewardData['rule'])
            && ($rewardData['rule'] == 'fixed_amount_discount'
                || $rewardData['rule'] == 'percentage_discount')
        ) {

            $totalAmount = $total->getSubtotal();
            if ($rewardData['rule'] == 'fixed_amount_discount') {
                $totalAmount = -$rewardData['reward_value'];
            } else {
                $totalAmount = -($totalAmount * $rewardData['reward_value'] / 100);
            }
            $total->addTotalAmount('zinrelo_discount', $totalAmount);
            $total->addBaseTotalAmount('zinrelo_discount', $totalAmount);
            $quote->setCustomDiscount($totalAmount);
            return $this;
        }
    }

    /**
     * Assign subtotal amount and label to address object
     *
     * @param Quote $quote
     * @param Total $total
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function fetch(Quote $quote, Total $total)
    {
        $rewardData = $this->helper->getRewardRulesData($quote);
        $rewardRules = [
            'fixed_amount_discount',
            'percentage_discount',
            'product_redemption',
        ];
        if (!empty($rewardData)
            && in_array($rewardData['rule'], $rewardRules)) {
            $totalAmount = $total->getSubtotal();
            if ($rewardData['rule'] == 'fixed_amount_discount') {
                $totalAmount = -$rewardData['reward_value'];
            } elseif ($rewardData['rule'] == 'percentage_discount') {
                $totalAmount = -$totalAmount * $rewardData['reward_value'] / 100;
            } elseif ($rewardData['rule'] == 'product_redemption') {
                $totalAmount = '';
            }
            return [
                'code' => 'zinrelo_discount',
                'title' => __($this->helper->getRewardAppliedRuleLabel($quote)),
                'value' => $totalAmount
            ];
        }
    }
}
