<?php

namespace Zinrelo\LoyaltyRewards\Cron;

use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Zinrelo\LoyaltyRewards\Helper\Data;

class RejectRedeemReward
{
    /**
     * @var QuoteFactory
     */
    private $quoteFactory;

    /**
     * @var TimezoneInterface
     */
    private $timezoneInterface;

    /**
     * @var Data
     */
    private $helper;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * AbandonedCart constructor.
     *
     * @param QuoteFactory $quoteFactory
     * @param Data $helper
     * @param TimezoneInterface $timezoneInterface
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        QuoteFactory $quoteFactory,
        Data $helper,
        TimezoneInterface $timezoneInterface,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->helper = $helper;
        $this->timezoneInterface = $timezoneInterface;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Send abandoned cart request to Zinrelo and Reject redeemed point
     *
     * @return bool
     * @throws LocalizedException|NoSuchEntityException
     */
    public function execute()
    {
        /** Get all customer's active cart data*/
        $event = $this->helper->getRewardEvents();
        if (in_array('cart_abandonment', $event, true)) {
            $quote = $this->quoteFactory->create()
                ->getCollection()
                ->addFieldToFilter('is_active', ['eq' => '1'])
                ->addFieldToFilter('customer_id', ['neq' => null])
                ->addFieldToFilter('is_abandoned_cart_sent', ['eq' => '2']);
            $carts = $quote->getItems();
            foreach ($carts as $cart) {
                $quote = $this->quoteRepository->get($cart->getId());
                $quoteTime = $quote->getUpdatedAt();
                $abandonedCartTime = $this->helper->getAbandonedCartTime();
                $rewardAppliedTime = date("Y-m-d H:i:s", strtotime("$quoteTime + $abandonedCartTime minute"));
                $currentTimeZone = $this->timezoneInterface->getConfigTimezone();
                $rewardAppliedTime = $this->timezoneInterface
                    ->date($rewardAppliedTime, $currentTimeZone)
                    ->format('Y-m-d H:i:s');
                $currentTime = $this->timezoneInterface->date()->format('Y-m-d H:i:s');
                if (strtotime($currentTime) >= strtotime($rewardAppliedTime) && count($quote->getItems())) {
                    /* Send reject reward request to Live API*/
                    if ($quote->getRedeemRewardDiscount()) {
                        $this->helper->sendRejectRewardRequest($quote);
                    }
                    /*Send Cart abandonment request custom event API*/
                    foreach ($quote->getItems() as $item) {
                        unset($item['stock_state_result']);
                        unset($item['product']);
                        $productId = $item['product_id'];
                        $item['product_url'] = $this->helper->getProductUrl($productId);
                        $item['product_image_url'] = $this->helper->getProductImageUrl($productId);
                        $categoryData = $this->helper->getCategoryData($productId);
                        $item['category_name'] = $categoryData['name'];
                        $item['category_ids'] = $categoryData['ids'];
                    }
                    $quoteData = $quote->debug();
                    $quoteData = $this->helper->setFormatedPrice($quoteData);
                    $quoteData['items_qty'] = (int) $quoteData['items_qty'];

                    $quoteData["coupon_code"] = $quote->getCouponCode() ? [$quote->getCouponCode()] : [];
                    unset(
                        $quoteData["reward_rules_data"],
                        $quoteData["redeem_reward_discount"]
                    );
                    $itemData = $quoteData["items"];
                    unset($quoteData["items"]);
                    foreach ($itemData as $key => $item) {
                        $item['qty'] = (int) $item['qty'];
                        $item = $this->helper->setFormatedPrice($item);
                        $quoteData["items"][] = $item;
                    }
                    $memberId = $quote->getCustomerId() ? $this->helper
                        ->getCustomerEmailById($quote->getCustomerId()) : "";
                    $params = [
                        "member_id" => $memberId,
                        "activity_id" => "cart_abandonment",
                        "data" => $quoteData
                    ];
                    $url = $this->helper->getWebHookUrl();
                    $params = $this->helper->json->serialize($params);
                    $this->helper->request($url, $params, "post");
                    $quote->setIsAbandonedCartSent(1);
                    $quote->save();
                }
            }
        }
    }
}
