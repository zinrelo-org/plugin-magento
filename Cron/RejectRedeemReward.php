<?php

namespace Zinrelo\LoyaltyRewards\Cron;

use Exception;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Quote\Model\QuoteFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Zinrelo\LoyaltyRewards\Helper\Data;
use Zinrelo\LoyaltyRewards\Logger\Logger;

class RejectRedeemReward
{
    public const COLLECTION_LIMIT = 100;
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
     * @var Logger
     */
    private $zinreloLogger;
    /**
     * @var AbandonedCartTime
     */
    private $abandonedCartTime;

    /**
     * AbandonedCart constructor.
     *
     * @param QuoteFactory $quoteFactory
     * @param Data $helper
     * @param TimezoneInterface $timezoneInterface
     * @param Logger $zinreloLogger
     */
    public function __construct(
        QuoteFactory $quoteFactory,
        Data $helper,
        TimezoneInterface $timezoneInterface,
        Logger $zinreloLogger
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->helper = $helper;
        $this->timezoneInterface = $timezoneInterface;
        $this->zinreloLogger = $zinreloLogger;
    }

    /**
     * Send abandoned cart request to Zinrelo and Reject redeemed point
     *
     * @return void
     */
    public function execute()
    {
        /** Get all customer's active cart data*/
        $event = $this->helper->getRewardEvents();
        $url = $this->helper->getWebHookUrl();
        $currentTimeZone = $this->timezoneInterface->getConfigTimezone();
        $this->abandonedCartTime = $this->helper->getAbandonedCartTime();
        $currentTime = $this->timezoneInterface->date()->format('Y-m-d H:i:s');

        if (in_array('cart_abandonment', $event, true)) {
            try {
                $quote = $this->getQuoteCollections();
                $quoteList = $quote->getItems();
                foreach ($quoteList as $item) {
                    $quoteObj = clone $item;
                    $quoteTime = $item->getUpdatedAt();
                    $rewardAppliedTime = $this->calculateTimeInterval($quoteTime, $currentTimeZone);
                    if (strtotime($currentTime) >= strtotime($rewardAppliedTime) && $item->getItemsCount()) {
                        /* Send reject reward request to Live API
                        We have to request event customer Quote wise so need to send an API one by one.*/
                        $zinreloQuote = $this->helper->getZinreloQuoteByQuoteId($item->getId());
                        if ($zinreloQuote->getRedeemRewardDiscount()) {
                            $this->helper->sendRejectRewardRequest($item);
                        }
                        /*Send Cart abandonment request custom event API*/
                        $itemsValues = [];
                        foreach ($item->getItemsCollection() as $quoteItem) {
                            $quoteItem->setData('product_url', $quoteItem->getProduct()->getProductUrl());
                            $quoteItem->setData('product_image_url', $this->helper
                                ->getImageFullUrl($quoteItem->getProduct()));
                            $quoteItem->setData('category_name', $this->helper
                                ->getCategoryName($quoteItem->getProduct()->getEntityId()));
                            $quoteItem->unsetData('stock_state_result');
                            $quoteItem->unsetData('product');
                            $itemsValues[] = $quoteItem->debug();
                        }
                        $quoteData = $item->debug();
                        $quoteData = $this->helper->setFormatedPrice($quoteData);
                        $quoteData['items_qty'] = (int)$quoteData['items_qty'];
                        $quoteData["coupon_code"] = $item->getCouponCode() ? [$item->getCouponCode()] : [];
                        $itemData = $itemsValues;
                        foreach ($itemData as $key => $qItem) {
                            $qItem['qty'] = (int)$qItem['qty'];
                            $qItem = $this->helper->setFormatedPrice($qItem);
                            $quoteData["items"][] = $qItem;
                        }
                        $memberId = $item->getCustomer() ? $item->getCustomer()->getEmail() : "";
                        $this->sendToZinrelo($memberId, $quoteData, $url, $quoteObj);
                    }
                }
            } catch (Exception $e) {
                $this->zinreloLogger->addErrorLog($e->getMessage());
            }
        }
    }

    /**
     * Get Quote Collections
     *
     * @return AbstractDb|AbstractCollection|null
     */
    public function getQuoteCollections()
    {
        try {
            $zinreloQuote = $this->helper->zinreloQuoteFactory->create()
                ->getCollection()
                ->addFieldToSelect(['quote_id'])
                ->addFieldToFilter('is_abandoned_cart_sent', ['eq' => '2']);
            $quoteIds = [];
            foreach ($zinreloQuote as $quote) {
                $quoteIds[] = $quote->getQuoteId();
            }
            $quote = $this->quoteFactory->create()
                ->getCollection()
                ->addFieldToFilter('is_active', ['eq' => '1'])
                ->addFieldToFilter('customer_id', ['neq' => null])
                ->addFieldToFilter('entity_id', ['in' => $quoteIds])
                ->setOrder(
                    'created_at',
                    'desc'
                );
            $quote->getSelect()->limit(self::COLLECTION_LIMIT);
            return $quote;
        } catch (Exception $e) {
            $this->zinreloLogger->addErrorLog($e->getMessage());
        }
    }

    /**
     * Calculate Time Interval
     *
     * @param mixed $quoteTime
     * @param mixed $currentTimeZone
     * @return string
     */
    public function calculateTimeInterval($quoteTime, $currentTimeZone)
    {
        $rewardAppliedTime = date("Y-m-d H:i:s", strtotime("$quoteTime + $this->abandonedCartTime minute"));
        $rewardAppliedTime = $this->timezoneInterface->date($rewardAppliedTime, $currentTimeZone)->format('Y-m-d H:i:s');
        // $rewardAppliedTime = $this->timezoneInterface->date(new \DateTime($rewardAppliedTime), $currentTimeZone)->format('Y-m-d H:i:s');
        return $rewardAppliedTime;
    }

    /**
     * Send To Zinrelo
     *
     * @param mixed $memberId
     * @param mixed $quoteData
     * @param mixed $url
     * @param mixed $object
     */
    public function sendToZinrelo($memberId, $quoteData, $url, $object)
    {
        try {
            $params = [
                "member_id" => $memberId,
                "activity_id" => "cart_abandonment",
                "data" => $quoteData
            ];
            $params = $this->helper->json->serialize($params);
            /*We have to request event customer Quote wise so need to send an API one by one.*/
            $this->helper->request($url, $params, "post");
            $zinreloQuote = $this->helper->getZinreloQuoteByQuoteId($object->getId());
            $zinreloQuote->setIsAbandonedCartSent(1);
            $zinreloQuote->save();
        } catch (Exception $e) {
            $this->zinreloLogger->addErrorLog($e->getMessage());
        }
    }
}
