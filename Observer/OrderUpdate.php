<?php

namespace Zinrelo\LoyaltyRewards\Observer;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Sales\Api\OrderRepositoryInterface;
use Zinrelo\LoyaltyRewards\Helper\Data;

class OrderUpdate implements ObserverInterface
{
    /**
     * @var Data
     */
    private $helper;
    /**
     * @var Http
     */
    private $request;
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;
    /**
     * @var Request
     */
    private $restRequest;

    /**
     * Order Update constructor.
     *
     * @param Data $helper
     * @param Http $request
     * @param Request $restRequest
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Data $helper,
        Http $request,
        Request $restRequest,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->helper = $helper;
        $this->request = $request;
        $this->orderRepository = $orderRepository;
        $this->restRequest = $restRequest;
    }

    /**
     * Order Update  event to zinrelo
     *
     * @param Observer $observer
     * @return bool|void
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        if (isset($this->request->getParam('history')['comment'])) {
            $id = $this->request->getParam('order_id') ?? $this->restRequest->getParam('id');
            $event = $this->helper->getRewardEvents();
            if (in_array('order_update', $event)) {
                $order = $this->orderRepository->get($id);
                $order->getPayment()->setMethodInstance();
                $orderData = $order->debug();
                $orderData = $this->helper->setFormatedPrice($orderData);
                $orderData['payment'] = $order->getPayment()->debug();
                unset($orderData['payment (Magento\Sales\Model\Order\Payment\Interceptor)']);

                $orderData['payment'] = $this->helper->setFormatedPrice($orderData['payment']);

                $orderData['last_comment'] = $this->request->getParam('history')['comment'];
                $replacedOrderId = $this->helper->getReplacedOrderID($order->getEntityId());
                $orderData["entity_id"] = $replacedOrderId;
                $orderData["order_id"] = $replacedOrderId;
                $orderData["coupon_code"] = $this->helper->getCouponCodes($order);
                unset($orderData['items']);
                foreach ($order->getItems() as $item) {
                    if ($item->getParentItemId()) {
                        continue;
                    }
                    $orderItemData = $item->debug();
                    $orderItemData['qty_ordered'] = (int) $orderItemData['qty_ordered'];
                    if (isset($orderItemData['qty_canceled'])) {
                        $orderItemData['qty_canceled'] = (int) $orderItemData['qty_canceled'];
                    }
                    if (isset($orderItemData['qty_invoiced'])) {
                        $orderItemData['qty_invoiced'] = (int) $orderItemData['qty_invoiced'];
                    }
                    if (isset($orderItemData['qty_refunded'])) {
                        $orderItemData['qty_refunded'] = (int) $orderItemData['qty_refunded'];
                    }
                    if (isset($orderItemData['qty_shipped'])) {
                        $orderItemData['qty_shipped'] = (int) $orderItemData['qty_shipped'];
                    }

                    $orderItemData = $this->helper->setFormatedPrice($orderItemData);

                    $orderItemData['order_id'] = $replacedOrderId;
                    $productId = $orderItemData['product_id'];
                    $orderItemData['product_url'] = $this->helper->getProductUrl($productId);
                    $orderItemData['product_image_url'] = $this->helper->getProductImageUrl($productId);
                    $categoryData = $this->helper->getCategoryData($productId);
                    $orderItemData['category_name'] = $categoryData['name'];
                    $orderItemData['category_ids'] = $categoryData['ids'];
                    $orderData['items'][] = $orderItemData;
                }

                if (isset($orderData["addresses"])) {
                    $addressesData = $orderData["addresses"];
                    unset($orderData["addresses"]);
                    foreach ($addressesData as $key => $address) {
                        $orderData["addresses"][] = $address;
                    }
                }
                unset($orderData['status_histories']);
                foreach ($order->getStatusHistories() as $statusHistories) {
                    $statusItemData = $statusHistories->debug();
                    $orderData['status_histories'][] = $statusItemData;
                }
                $orderData['total_qty_ordered'] = (int) $orderData['total_qty_ordered'];
                $params = [
                    "member_id" => $order->getCustomerEmail(),
                    "activity_id" => "order_update",
                    "data" => $orderData
                ];
                $url = $this->helper->getWebHookUrl();
                $params = $this->helper->json->serialize($params);
                $this->helper->request($url, $params, "post");
                return true;
            }
        }
    }
}
