<?php

namespace Zinrelo\LoyaltyRewards\Observer;

use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Zinrelo\LoyaltyRewards\Helper\Data;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use function SpomkyLabs\Pki\ASN1\Type\string;

class OrderShipped implements ObserverInterface
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var ShipmentRepositoryInterface
     */
    private $shipmentRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * Order shipped constructor.
     *
     * @param Data $helper
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ShipmentRepositoryInterface $shipmentRepository
     */
    public function __construct(
        Data $helper,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ShipmentRepositoryInterface $shipmentRepository
    ) {
        $this->helper = $helper;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->shipmentRepository = $shipmentRepository;
    }

    /**
     * Order shipped event to zinrelo
     *
     * @param Observer $observer
     * @return bool
     */
    public function execute(Observer $observer)
    {
        $event = $this->helper->getRewardEvents();
        if (in_array('order_shipped', $event)) {
            $shipment = $observer->getEvent()->getShipment();
            $order = $shipment->getOrder();
            $this->orderShippedData($order);

        }
    }

    /**
     * Order Shipped Data
     *
     * @param mixed $order
     * @return bool
     */
    public function orderShippedData($order)
    {
        if (!$order->canShip()) {
            try {
                $searchCriteria = $this->searchCriteriaBuilder
                    ->addFilter('order_id', $order->getEntityId())->create();
                $shipment = $this->shipmentRepository->getList($searchCriteria);
                $shipmentItems = $shipment->toArray();
                $couponCode = $this->helper->getCouponCodes($order);
                $replacedOrderId = $this->helper->getReplacedOrderID($order->getEntityId());
                foreach ($shipmentItems["items"] as $key => $shipmentItem) {
                    $shipmentItems['items'][$key]['total_qty'] = (int)$shipmentItems['items'][$key]['total_qty'];
                    $shipmentItems = $this->helper->setFormatedPrice($shipmentItems);
                    $shipmentItems["items"][$key]["order_id"] = $replacedOrderId;
                    $shipmentData = $this->shipmentRepository->get($shipmentItem["entity_id"]);
                    $totalDiscountAmount = 0;
                    $totalBaseDiscountAmount = 0;
                    foreach ($order->getAllItems() as $item) {
                        if ($item->getParentItemId()) {
                            continue;
                        }
                        $discAmount[$item->getSku()] = $item->getDiscountAmount();
                        $totalDiscountAmount += $discAmount[$item->getSku()];
                    }
                    foreach ($order->getAllItems() as $item) {
                        if ($item->getParentItemId()) {
                            continue;
                        }
                        $discBaseAmount[$item->getSku()] = $item->getBaseDiscountAmount();
                        $totalBaseDiscountAmount += $discBaseAmount[$item->getSku()];
                    }
                    foreach ($order->getAllItems() as $item) {
                        if ($item->getParentItemId()) {
                            continue;
                        }
                        $BasePrice[$item->getSku()] = $item->getBasePrice();
                    }
                    foreach ($shipmentData->getItems() as $item) {
                        unset($item['shipment']);
                        if ($item->getParentItemId()) {
                            continue;
                        }
                        $subTotal = $item->getPrice() * (string)$item->getQty();
                        $baseSubTotal = $BasePrice[$item->getSku()] * (string)$item->getQty();
                        $shipmentItemData = $item->debug();
                        $shipmentItemData['qty'] = (int)$shipmentItemData['qty'];
                        $shipmentItemData = $this->helper->setFormatedPrice($shipmentItemData);
                        $productId = $shipmentItemData['product_id'];
                        $shipmentItemData['base_price'] = $BasePrice[$item->getSku()];
                        $shipmentItemData['discount_amount'] = $discAmount[$item->getSku()] ?? 0.00;
                        $shipmentItemData['base_discount_amount'] = $discBaseAmount[$item->getSku()] ?? 0.00;
                        $shipmentItemData['subtotal'] = $subTotal;
                        $shipmentItemData['base_subtotal'] = $baseSubTotal;
                        $shipmentItemData['product_url'] = $this->helper->getProductUrl($productId);
                        $shipmentItemData['product_image_url'] = $this->helper->getProductImageUrl($productId);
                        $categoryData = $this->helper->getCategoryData($productId);
                        $shipmentItemData['category_name'] = $categoryData['name'];
                        $shipmentItemData['category_ids'] = $categoryData['ids'];
                        $shipmentItems["items"][$key]['items'][] = $shipmentItemData;
                    }
                    foreach ($shipmentData->getTracks() as $track) {
                        $trackData = $track->debug();
                        $trackData["order_id"] = $replacedOrderId;
                        $shipmentItems["items"][$key]['track'][] = $trackData;
                    }
                }
                $shipmentItems['coupon_code'] = $couponCode;
                $shipmentItems['order_id'] = $replacedOrderId;
                $shipmentItems['discount_amount'] = $totalDiscountAmount ?? 0.00;
                $shipmentItems['base_discount_amount'] = $totalBaseDiscountAmount ?? 0.00;
                $shipmentItems['order_subtotal'] = (float)$order->getSubtotal();
                $shipmentItems['subtotal'] = (float)$order->getSubtotal();
                $shipmentItems['base_subtotal'] = (float)$order->getBaseSubtotal();
                $shipmentItems['order_base_subtotal'] = (float)$order->getBaseSubtotal();
                $shipmentItems['order_grand_total'] = (float)$order->getGrandTotal();
                $shipmentItems['order_base_grand_total'] = (float)$order->getBaseGrandTotal();
                $params = [
                    "member_id" => $order->getCustomerEmail(),
                    "activity_id" => "order_shipped",
                    "data" => $shipmentItems
                ];
                $url = $this->helper->getWebHookUrl();
                $params = $this->helper->json->serialize($params);
                $this->helper->request($url, $params, "post");
                return true;
            } catch (Exception $e) {
                return false;
            }
        }
    }
}
