<?php

namespace Zinrelo\LoyaltyRewards\Block\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template;
use Zinrelo\LoyaltyRewards\Helper\Data;
use Zinrelo\LoyaltyRewards\Logger\Logger as ZinreloLogger;

class RewardList extends Template
{
    /**
     * @var Data
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
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * RewardList constructor.
     *
     * @param Template\Context $context
     * @param Data $helper
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param ZinreloLogger $logger
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Data $helper,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        ZinreloLogger $logger,
        array $data = []
    ) {
        $this->logger = $logger;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
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
        if (!empty($quote->getRewardRulesData() && !empty($quote->getRedeemRewardDiscount()))) {
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
        try {
            $customerId = $this->customerSession->getCustomer()->getId();
            $customerEmail = $this->helper->getCustomerEmailById($customerId);
            $url = $this->helper->getLiveWebHookUrl() . "members/" . $customerEmail;
            $url = $this->helper->getIdParam($url);
            $response = $this->helper->request($url, "", "get", "live_api");
            if ($response) {
                if ($response["success"] && !empty($response["result"]["data"]["available_points"])) {
                    $point = $response["result"]["data"]["available_points"];
                    return $point > 0 ? $point : "error";
                } else {
                    return "error";
                }
            }
        } catch (Exception $e) {
            if ($this->helper->enableCustomLog()) {
                $this->logger->critical($e->getMessage());
            }
            return "error";
        }
    }

    /**
     * Get Customer Id
     *
     * @return mixed
     */
    public function getCustomerId()
    {
        return $this->customerSession->getCustomer()->getId();
    }

    /**
     * Get reward point rules
     *
     * @return array
     */
    public function getRedeemRules()
    {
        $customerId = $this->customerSession->getCustomer()->getId();
        $customerEmail = $this->helper->getCustomerEmailById($customerId);
        $url = $this->helper->getLiveWebHookUrl() . "members/" . $customerEmail . "/rewards";
        $url = $this->helper->getIdParam($url);
        $response = $this->helper->request($url, "", "get", "live_api");
        $rewardRules = [];
        if ($response && $response["success"] && !empty($response["result"]["data"]["rewards"])) {
            $rules = $response["result"]["data"]["rewards"];
            $rewardTypes = $this->getDefaultRewardTypes();
            $availablePoint = $this->getRewardPoints();
            $quote = $this->helper->getQuote();
            $subTotal = $quote->getSubtotal();
            foreach ($rules as $rule) {
                if (($rule['reward_sub_type'] == 'Fixed Amount Discount' && $rule['reward_value'] > $subTotal) ||
                    $availablePoint < $rule['points_to_be_redeemed']
                ) {
                    continue;
                }
                if (in_array($rule["reward_sub_type"], array_values($rewardTypes), true)) {
                    foreach ($rewardTypes as $key => $value) {
                        if ($rule["reward_sub_type"] == $value) {
                            $rewardRules[$rule["reward_id"]] = [
                                "rule" => $key,
                                "reward_id" => $rule["reward_id"],
                                "id" => '',
                                "reward_name" => $rule["reward_name"],
                                "reward_value" => !empty($rule["reward_value"]) ? $rule["reward_value"] : "",
                                "product_id" => isset($rule["product_id"]) ? $rule["product_id"] : ""
                            ];
                        }
                    }
                }
            }
        }
        return $rewardRules;
    }

    /**
     * Get default reward to apply for redeem
     *
     * @return array
     */
    public function getDefaultRewardTypes(): array
    {
        return [
            "product_redemption" => "Product Redemption",
            "fixed_amount_discount" => "Fixed Amount Discount",
            "percentage_discount" => "Percentage Discount",
            "free_shipping" => "Free Shipping"
        ];
    }
}
