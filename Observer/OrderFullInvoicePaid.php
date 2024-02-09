<?php

namespace Zinrelo\LoyaltyRewards\Observer;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\OrderFactory;
use Zinrelo\LoyaltyRewards\Helper\Data;

class OrderFullInvoicePaid implements ObserverInterface
{
    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;
    /**
     * @var Data
     */
    private $helper;
    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;
    /**
     * @var Http
     */
    private $request;
    /**
     * @var OrderFactory
     */
    private $orderFactory;
    /**
     * @var RestRequest
     */
    private $restRequest;

    /**
     * OrderFullInvoicePaid constructor.
     *
     * @param Data $helper
     * @param Http $request
     * @param OrderFactory $orderFactory
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RestRequest $restRequest
     */
    public function __construct(
        Data $helper,
        Http $request,
        OrderFactory $orderFactory,
        InvoiceRepositoryInterface $invoiceRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RestRequest $restRequest
    ) {
        $this->helper = $helper;
        $this->request = $request;
        $this->orderFactory = $orderFactory;
        $this->invoiceRepository = $invoiceRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->restRequest = $restRequest;
    }

    /**
     * Order complete event to zinrelo
     *
     * @param Observer $observer
     * @return bool
     */
    public function execute(Observer $observer)
    {
        $event = $this->helper->getRewardEvents();
        if (in_array('order_paid', $event, true)) {
            $orderId = $this->request->getParam('order_id');
            if (empty($orderId)) {
                $orderId = $this->restRequest->getParam('orderId');
            }
            $order = $this->orderFactory->create()->load($orderId);
            $this->orderFullInvoiceData($order, $orderId);
        }
    }

    /**
     * Order Full Invoice Data
     *
     * @param mixed $order
     * @param mixed $orderId
     * @return bool
     */
    public function orderFullInvoiceData($order, $orderId)
    {
        if (!$order->canInvoice()) {
            try {
                $searchCriteria = $this->searchCriteriaBuilder->addFilter('order_id', $orderId)->create();
                $invoices = $this->invoiceRepository->getList($searchCriteria);
                $invoicesItems = $invoices->toArray();
                $couponCode = $this->helper->getCouponCodes($order);
                $replacedOrderId = $this->helper->getReplacedOrderID($orderId);
                foreach ($invoicesItems["items"] as $key => $invoiceItem) {
                    $invoicesItems["items"][$key] = $this->helper->setFormatedPrice($invoicesItems["items"][$key]);
                    $invoicesItems["items"][$key]['total_qty'] = (int)$invoicesItems["items"][$key]['total_qty'];
                    $invoicesItems["items"][$key]["order_id"] = $replacedOrderId;
                    $invoiceData = $this->invoiceRepository->get($invoiceItem["entity_id"]);
                    foreach ($invoiceData->getItems() as $item) {
                        if ($item->getOrderItem()->getParentItem()) {
                            continue;
                        }
                        unset($item['invoice']);
                        $invoiceItemData = $item->debug();
                        $invoiceItemData['qty'] = (int)$invoiceItemData['qty'];
                        $invoiceItemData = $this->helper->setFormatedPrice($invoiceItemData);
                        $productId = $invoiceItemData['product_id'];
                        $invoiceItemData['product_url'] = $this->helper->getProductUrl($productId);
                        $invoiceItemData['product_image_url'] = $this->helper->getProductImageUrl($productId);
                        $categoryData = $this->helper->getCategoryData($productId);
                        $invoiceItemData['category_name'] = $categoryData['name'];
                        $invoiceItemData['category_ids'] = $categoryData['ids'];
                        $invoicesItems["items"][$key]['items'][] = $invoiceItemData;
                    }
                }
                $invoicesItems['coupon_code'] = $couponCode;
                $invoicesItems['order_id'] = $replacedOrderId;
                $params = [
                    "member_id" => $order->getCustomerEmail(),
                    "activity_id" => "order_paid",
                    "data" => $invoicesItems
                ];
                $url = $this->helper->getWebHookUrl();
                $params = $this->helper->json->serialize($params);
                $this->helper->request($url, $params, "post");
                /* Earn reward on order full paid */
                /*$this->helper->orderPurchaseRequest($order, $replacedOrderId);*/
                return true;
            } catch (Exception $e) {
                return false;
            }
        }
    }
}
