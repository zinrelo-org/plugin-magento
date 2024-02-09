<?php

namespace Zinrelo\LoyaltyRewards\Observer;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Zinrelo\LoyaltyRewards\Helper\Data;

class OrderRefund implements ObserverInterface
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
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;
    /**
     * @var OrderFactory
     */
    private $orderFactory;
    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * Customer Register constructor.
     *
     * @param Data $helper
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderFactory $orderFactory
     * @param CreditmemoRepositoryInterface $creditMemoRepository
     * @param ProductMetadataInterface $productMetadata
     */
    public function __construct(
        Data $helper,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderFactory $orderFactory,
        CreditmemoRepositoryInterface $creditMemoRepository,
        ProductMetadataInterface $productMetadata
    ) {
        $this->helper = $helper;
        $this->orderFactory = $orderFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->creditMemoRepository = $creditMemoRepository;
        $this->productMetadata = $productMetadata;
    }

    /**
     * Order refund  event to zinrelo
     *
     * @param Observer $observer
     * @return bool
     */
    public function execute(Observer $observer)
    {
        $version = $this->productMetadata->getVersion();
        $event = $this->helper->getRewardEvents();
        if (in_array('order_refund', $event)) {
            try {
                /*This event should call only for Magento version 2.3.0*/
                if ($observer->getEvent()->getName() == "sales_order_creditmemo_save_after" && $version != "2.3.0") {
                    return;
                }
                $refund = $observer->getEvent()->getCreditmemo();
                if ($refund->getTotalQty() > 0) {
                    $orderId = $observer->getEvent()->getCreditmemo()->getOrderId();
                    $order = $this->orderFactory->create()->load($orderId);
                    if (!$order->canCreditmemo() && $order->getCreditmemosCollection()->count() == 1) {
                        /*For Full refund*/
                        $searchCriteria = $this->searchCriteriaBuilder->addFilter('order_id', $orderId)->create();
                        $refund = $this->creditMemoRepository->getList($searchCriteria);
                        $refundsItems = $refund->toArray();
                        $replacedOrderId = $this->helper->getReplacedOrderID($orderId);
                        $refundsItems = $this->manageFullRefundItems($refundsItems, $replacedOrderId);
                        $refundsItems["order_id"] = $replacedOrderId;
                        $refundsItems["coupon_code"] = $this->helper->getCouponCodes($order);
                        $this->sendRequest(
                            $order->getCustomerEmail(),
                            "full_order_refund",
                            $refundsItems
                        );
                        return true;
                    } else {
                        /*For Partial refund*/
                        $refund = $refund->debug();
                        $refundData["items"][0] = $refund;
                        $replacedOrderId = $this->helper->getReplacedOrderID($refund["order_id"]);
                        $itemData = $refund["items"];
                        $refundData["order_id"] = $replacedOrderId;
                        $refundData["coupon_code"] = $this->helper->getCouponCodes($order);
                        unset($refundData["items"][0]["items"]);
                        $refundData = $this->managePartialRefundItems($itemData, $refundData);
                        $this->sendRequest($order->getCustomerEmail(), "partial_order_refund", $refundData);
                        return true;
                    }
                }
            } catch (Exception $e) {
                return false;
            }
        }
    }

    /**
     * Manage items and return updated items
     *
     * @param array $refundsItems
     * @param mixed $replacedOrderId
     * @return array
     */
    public function manageFullRefundItems($refundsItems, $replacedOrderId)
    {
        foreach ($refundsItems["items"] as $key => $refundItem) {
            $refundsItems["items"][$key] = $this->helper->setFormatedPrice($refundsItems["items"][$key]);
            $refundsItems["items"][$key]["order_id"] = $replacedOrderId;
            $refundData = $this->creditMemoRepository->get($refundItem["entity_id"]);
            foreach ($refundData->getItems() as $item) {
                unset($item['refund']);
                if ($item->getOrderItem()->getParentItem()) {
                    continue;
                }
                $refundItemData = $item->debug();
                $refundItemData = $this->helper->setFormatedPrice($refundItemData);
                $productId = $refundItemData['product_id'];
                $refundItemData['product_url'] = $this->helper->getProductUrl($productId);
                $refundItemData['product_image_url'] = $this->helper->getProductImageUrl($productId);
                $categoryData = $this->helper->getCategoryData($productId);
                $refundItemData['category_name'] = $categoryData['name'];
                $refundItemData['category_ids'] = $categoryData['ids'];
                $refundsItems["items"][$key]['items'][] = $refundItemData;
            }
        }
        return $refundsItems;
    }

    /**
     * Send request to zinrelo when refund generated
     *
     * @param string $customerEmail
     * @param string $activityId
     * @param array $refundData
     * @return bool
     */
    public function sendRequest($customerEmail, $activityId, $refundData)
    {
        $params = [
            "member_id" => $customerEmail,
            "activity_id" => $activityId,
            "data" => $refundData
        ];
        $url = $this->helper->getWebHookUrl();
        $params = $this->helper->json->serialize($params);
        $this->helper->request($url, $params, "post");
        return true;
    }

    /**
     * Manage items and return updated items
     *
     * @param array $itemData
     * @param array $refundData
     * @return array
     */
    public function managePartialRefundItems($itemData, $refundData)
    {
        foreach ($itemData as $item) {
            if (isset($item) && $item['price'] <= 0) {
                continue;
            }
            $item = $this->helper->setFormatedPrice($item);
            if ($item["qty"]) {
                $item['qty'] = (int)$item['qty'];
                $productId = $item['product_id'];
                $item["product_url"] = $this->helper->getProductUrl($productId);
                $item["product_image_url"] = $this->helper->getProductImageUrl($productId);
                $categoryData = $this->helper->getCategoryData($productId);
                $item['category_name'] = $categoryData['name'];
                $item['category_ids'] = $categoryData['ids'];
                $refundData["items"][0]["items"][] = $item;
            }
        }
        $refundData["items"][0] = $this->helper->setFormatedPrice($refundData["items"][0]);
        return $refundData;
    }
}
