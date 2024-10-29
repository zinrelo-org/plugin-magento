<?php

namespace Zinrelo\LoyaltyRewards\Block\Cart;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Zinrelo\LoyaltyRewards\Helper\Reward;
use Zinrelo\LoyaltyRewards\Logger\Logger as ZinreloLogger;

class RewardList extends Template
{
    /**
     * @var Reward
     */
    public $helper;

    /**
     * @var ZinreloLogger
     */
    public $logger;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * RewardList constructor.
     *
     * @param Template\Context $context
     * @param Reward $helper
     * @param CustomerSession $customerSession
     * @param ZinreloLogger $logger
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Reward $helper,
        CustomerSession $customerSession,
        ZinreloLogger $logger,
        array $data = []
    ) {
        $this->logger = $logger;
        $this->customerSession = $customerSession;
        $this->helper = $helper;
        parent::__construct(
            $context,
            $data
        );
    }

    /**
     * Get controller action to redeem reward point
     *
     * @return string
     */
    public function getFormAction()
    {
        return $this->getUrl('loyaltyRewards/loyaltyrewards/applyPoint', ['_secure' => true]);
    }

    /**
     * Check applied rule can be canceled or not
     *
     * @return bool
     */
    public function canCancelRedeem()
    {
        $quote = $this->helper->getQuote();
        $zinreloQuote = $this->helper->getZinreloQuoteByQuoteId($quote->getId());
        if (!empty($zinreloQuote->getRewardRulesData() && !empty($zinreloQuote->getRedeemRewardDiscount()))) {
            return true;
        }
        return false;
    }

    /**
     * Get available reward points
     *
     * @return mixed
     */

    public function getRewardPoints()
    {
        return $this->helper->getRewardPoints();
    }

    /**
     * Get reward point rules
     *
     * @return array
     */
    public function getRedeemRules()
    {
        return $this->helper->getRedeemRules();
    }

    /**
     * Get Customer Email
     *
     * @return string
     */
    public function getCustomerEmail()
    {
        return $this->customerSession->getCustomer()->getEmail();
    }

    /**
     * Get Reward Rules Data
     *
     * @param mixed $quote
     * @param mixed $redeemReward
     * @return mixed
     */
    public function getRewardRulesData($quote, $redeemReward)
    {
        return $this->helper->getRewardRulesData($quote, $redeemReward);
    }
}
