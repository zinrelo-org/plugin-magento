<?php

namespace Zinrelo\LoyaltyRewards\Helper;

use Exception;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\ImageFactory;
use Magento\Catalog\Model\ProductCategoryList;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\SessionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Item\CollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Zinrelo\LoyaltyRewards\Logger\Logger as ZinreloLogger;

class Data extends AbstractHelper
{
    public const XML_PATH_LOYALTY_REWARDS_ACTIVE = "zinrelo_loyaltyRewards/settings/loyalty_rewards_active";
    public const XML_PATH_WEB_HOOK_URL = "zinrelo_loyaltyRewards/settings/web_hook_url";
    public const XML_PATH_WEBHOOK_INTEGRATION_ID = 'zinrelo_loyaltyRewards/settings/webhook_integration_id';
    public const XML_PATH_WEBHOOK_INTEGRATION_URL = 'zinrelo_loyaltyRewards/settings/webhook_integration_url';
    public const XML_PATH_LIVE_WEB_HOOK_URL = "zinrelo_loyaltyRewards/settings/live_web_hook_url";
    public const XML_PATH_ABANDONED_CART_TIME = "zinrelo_loyaltyRewards/settings/abandoned_cart_time";
    public const XML_PATH_PARTNER_ID = "zinrelo_loyaltyRewards/settings/partner_id";
    public const XML_PATH_API_KEY = "zinrelo_loyaltyRewards/settings/api_key";
    public const XML_PATH_API_KEY_IDENTIFIER = "zinrelo_loyaltyRewards/settings/api_key_identifier";
    public const XML_PATH_REWARD_EVENTS = "zinrelo_loyaltyRewards/settings/reward_events";
    public const XML_PATH_REWARDS_DROPDOWN_ACTIVE = "zinrelo_loyaltyRewards/settings/rewards_event_drop_down_active";
    public const XML_PATH_REWARDS_POINTS_AT_PDP = "zinrelo_loyaltyRewards/settings/product_page_rewards_point_enable";
    public const XML_PATH_FREE_SHIPPING_LABEL = "zinrelo_loyaltyRewards/settings/free_shipping_label";
    public const XML_PATH_PRODUCT_PAGE_REWARD_LABEL = "zinrelo_loyaltyRewards/settings/product_page_reward_label";
    public const XML_PATH_CART_PAGE_REWARD_DROPDOWN_LABEL =
        "zinrelo_loyaltyRewards/settings/cart_page_reward_dropdown_label";
    public const XML_PATH_LANGUAGES = 'zinrelo_loyaltyRewards/settings/languages_mapping';
    public const XML_PATH_ENABLE_CUSTOM_LOG = 'zinrelo_loyaltyRewards/settings/enable_log';

    /**
     * @var Json
     */
    public $json;
    /**
     * @var ScopeConfigInterface $scopeConfig
     */
    protected $scopeConfig;
    /**
     * @var WriterInterface $writeConfig
     */
    protected $writeConfig;
    /**
     * @var SessionFactory
     */
    protected $customerSession;
    /**
     * @var CurlFactory
     */
    private $curl;
    /**
     * @var ZinreloLogger
     */
    private $logger;
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
    /**
     * @var TimezoneInterface
     */
    private $timezoneInterface;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var ProductCategoryList
     */
    private $productCategory;
    /**
     * @var CategoryRepositoryInterface
     */
    private $categoryRepository;
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;
    /**
     * @var QuoteFactory
     */
    private $quoteFactory;
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;
    /**
     * @var RequestInterface
     */
    private $request;
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;
    /**
     * @var Repository
     */
    private $assetRepos;
    /**
     * @var ImageFactory
     */
    private $helperImageFactory;
    /**
     * @var Escaper
     */
    private $escaper;
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;
    /**
     * @var CollectionFactory
     */
    private $orderItem;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param ZinreloLogger $logger
     * @param CurlFactory $curl
     * @param RequestInterface $request
     * @param ProductCategoryList $productCategory
     * @param CustomerRepositoryInterface $customerRepository
     * @param TimezoneInterface $timezoneInterface
     * @param ProductRepositoryInterface $productRepository
     * @param SessionFactory $customerSession
     * @param CheckoutSession $checkoutSession
     * @param QuoteFactory $quoteFactory
     * @param Repository $assetRepos
     * @param Escaper $escaper
     * @param ImageFactory $helperImageFactory
     * @param CartRepositoryInterface $quoteRepository
     * @param CollectionFactory $orderItem
     * @param OrderRepositoryInterface $orderRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $writeConfig
     * @param Json $json
     */
    public function __construct(
        Context $context,
        ZinreloLogger $logger,
        CurlFactory $curl,
        RequestInterface $request,
        ProductCategoryList $productCategory,
        CustomerRepositoryInterface $customerRepository,
        TimezoneInterface $timezoneInterface,
        ProductRepositoryInterface $productRepository,
        SessionFactory $customerSession,
        CheckoutSession $checkoutSession,
        QuoteFactory $quoteFactory,
        Repository $assetRepos,
        Escaper $escaper,
        ImageFactory $helperImageFactory,
        CartRepositoryInterface $quoteRepository,
        CollectionFactory $orderItem,
        OrderRepositoryInterface $orderRepository,
        CategoryRepositoryInterface $categoryRepository,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        WriterInterface $writeConfig,
        Json $json
    ) {
        $this->curl = $curl;
        $this->escaper = $escaper;
        $this->request = $request;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        $this->quoteFactory = $quoteFactory;
        $this->customerRepository = $customerRepository;
        $this->categoryRepository = $categoryRepository;
        $this->productCategory = $productCategory;
        $this->timezoneInterface = $timezoneInterface;
        $this->productRepository = $productRepository;
        $this->customerSession = $customerSession;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->writeConfig = $writeConfig;
        $this->json = $json;
        $this->helperImageFactory = $helperImageFactory;
        $this->assetRepos = $assetRepos;
        $this->quoteRepository = $quoteRepository;
        $this->orderItem = $orderItem;
        parent::__construct($context);
    }

    /**
     * Set zinrelo reward when order from Admin, and get OrderID for Zinrelo
     *
     * @param mixed $orderId
     * @param bool $isset
     * @return string
     */
    public function getReplacedOrderID($orderId, $isset = true)
    {
        $order = $this->orderRepository->get($orderId);
        if (!$isset) {
            $order->setZinreloReward("{}");
            $order->save();
        }
        return $order->getIncrementId();
    }

    /**
     * Get Redeem Reward Discount Data
     *
     * @param string $orderId
     * @return array
     */
    public function getRedeemRewardDiscountData($orderId)
    {
        $order = $this->orderRepository->get($orderId);
        $quoteID = $order->getQuoteId();
        $quote = $this->quoteFactory->create()->load($quoteID);
        $redeemReward = $quote->getRedeemRewardDiscount();
        $rewardData = $this->getRewardRulesData($quote, $redeemReward);
        $discountAmount = "";
        $discountLabel = "";
        if (isset($rewardData['rule'])
            && ($rewardData['rule'] == 'fixed_amount_discount'
                || $rewardData['rule'] == 'percentage_discount')) {
            $totalAmount = $order->getSubtotal();
            if ($rewardData['rule'] == 'fixed_amount_discount') {
                $discountAmount = -$rewardData['reward_value'];
            } else {
                $discountAmount = -$totalAmount * $rewardData['reward_value'] / 100;
            }
            $discountLabel = $this->getRewardAppliedRuleLabel($quote);
        }
        return [
            "status" => $discountAmount == "" ? false : true,
            "value" => $discountAmount,
            "label" => $discountLabel
        ];
    }

    /**
     * Get Applied Rule Data
     *
     * @param mixed $quote
     * @param string $ruleId
     * @return mixed
     */
    public function getRewardRulesData($quote, $ruleId = "")
    {
        if ($ruleId == "" && $quote) {
            $ruleId = $quote->getRedeemRewardDiscount();
        }

        if ($quote) {
            if ($quote->getRewardRulesData()) {
                $rewardRules = $this->json->unserialize($quote->getRewardRulesData());
                return $rewardRules[$ruleId] ?? [];
            }
        }
        return [];
    }

    /**
     * Get applied reward rule name
     *
     * @param Quote $quote
     * @return string
     */
    public function getRewardAppliedRuleLabel($quote): string
    {
        $rewardData = $this->getRewardRulesData($quote);
        return $this->escaper->escapeHtml($rewardData['reward_name']);
    }

    /**
     * Get Collect Reward Value Data
     *
     * @param mixed $orderId
     * @param mixed $flag
     * @param mixed $actionData
     * @return $this
     */
    public function getCollectRewardValueData($orderId, $flag, $actionData)
    {
        $order = $this->orderRepository->get($orderId);
        $quoteID = $order->getQuoteId();
        $quote = $this->quoteFactory->create()->load($quoteID);
        $redeemReward = $quote->getRedeemRewardDiscount();
        $rewardData = $this->getRewardRulesData($quote, $redeemReward);
        if (isset($rewardData['rule'])
            && ($rewardData['rule'] == 'fixed_amount_discount'
                || $rewardData['rule'] == 'percentage_discount')) {

            $totalAmount = $order->getSubtotal();
            if ($rewardData['rule'] == 'fixed_amount_discount') {
                $totalAmount = $rewardData['reward_value'];
            } else {
                $totalAmount = $totalAmount * $rewardData['reward_value'] / 100;
            }
            if ($flag == 'invoice') {
                $invoiceId = '';
                foreach ($order->getInvoiceCollection() as $invoice) {
                    $invoiceId = $invoice->getIncrementId();
                }
                if (!$invoiceId) {
                    $actionData->setGrandTotal($actionData->getGrandTotal() - $totalAmount);
                    $actionData->setBaseGrandTotal($actionData->getBaseGrandTotal() - $totalAmount);
                }
            }
            return $this;
        }
    }

    /**
     * Get idParam
     *
     * @param string $url
     * @return string
     */
    public function getIdParam($url): string
    {
        $params = [
            "idParam" => "member_id"
        ];
        $url .= "?" . http_build_query($params);
        return $url;
    }

    /**
     * Check Is Reward DropDown Enable
     *
     * @return bool
     */
    public function isRewardDropDownEnable()
    {
        $isModuleEnable = $this->isModuleEnabled();
        $isRewardDropDownEnable = $this->getConfig(self::XML_PATH_REWARDS_DROPDOWN_ACTIVE) ?? false;
        return ($isRewardDropDownEnable && $isModuleEnable) ?? false;
    }

    /**
     * Check module is enabled or disabled
     *
     * @return bool
     */
    public function isModuleEnabled()
    {
        return $this->getConfig(self::XML_PATH_LOYALTY_REWARDS_ACTIVE) ? true : false;
    }

    /**
     * Get Config
     *
     * @param mixed $config_path
     * @return mixed
     */
    public function getConfig($config_path)
    {
        return $this->scopeConfig->getValue(
            $config_path,
            ScopeInterface::SCOPE_STORE,
            $this->storeManager->getStore()->getStoreId()
        );
    }

    /**
     * Check Reward Point can show at PDP or not
     *
     * @return bool
     */
    public function isRewardPointAtPdpEnabled()
    {
        $isModuleEnable = $this->isModuleEnabled();
        $isRewardPointAtPdpEnabled = $this->getConfig(self::XML_PATH_REWARDS_POINTS_AT_PDP) ?? false;
        return ($isRewardPointAtPdpEnabled && $isModuleEnable) ?? false;
    }

    /**
     * Get Label for show at Product view  Pages
     *
     * @return string
     */
    public function getRewardLabelAtProductPage(): string
    {
        return $this->getConfig(self::XML_PATH_PRODUCT_PAGE_REWARD_LABEL);
    }

    /**
     * Get Label for show at cart page reward list dropdown section
     *
     * @return string
     */
    public function getRewardLabelAtCartPage(): string
    {
        return $this->getConfig(self::XML_PATH_CART_PAGE_REWARD_DROPDOWN_LABEL);
    }

    /**
     * Get Free Shipping Label
     *
     * @return mixed
     */
    public function getFreeShippingLabel()
    {
        return $this->getConfig(self::XML_PATH_FREE_SHIPPING_LABEL);
    }

    /**
     * Get Abandoned Cart Time
     *
     * @return mixed
     */
    public function getAbandonedCartTime()
    {
        return $this->getConfig(self::XML_PATH_ABANDONED_CART_TIME);
    }

    /**
     * Get Customer By Id
     *
     * @param mixed $customerId
     * @return string
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getCustomerEmailById($customerId)
    {
        if ($customerId) {
            try {
                return $this->customerRepository->getById($customerId)->getEmail();
            } catch (Exception $e) {
                $this->addErrorLog($e->getMessage());
            }
        }
        return "";
    }

    /**
     * Add log when getting error on get-set Data
     *
     * @param mixed $logData
     */
    public function addErrorLog($logData)
    {
        if ($this->enableCustomLog()) {
            $this->logger->critical($logData);
        }
    }

    /**
     * Enable Custom Log
     *
     * @return mixed
     */
    public function enableCustomLog()
    {
        return $this->getConfig(self::XML_PATH_ENABLE_CUSTOM_LOG);
    }

    /**
     * Get Free Product
     *
     * @return mixed
     */
    public function getFreeProduct()
    {
        $quote = $this->checkoutSession->getQuote();
        $rewardData = $this->getRewardRulesData($quote);
        if ($rewardData) {
            return $rewardData["product_id"];
        }
        return '';
    }

    /**
     * Send Reject reward point API request to Zinrelo when cart gone Abandoned or Redeem Cancel button clicked
     *
     * @param Quote $quote
     * @return bool
     */
    public function sendRejectRewardRequest($quote)
    {
        $redeemReward = $quote->getRedeemRewardDiscount();
        $rewardData = $this->getRewardRulesData($quote, $redeemReward);
        if (!empty($rewardData)) {
            $url = $this->getLiveWebHookUrl() . "transactions/" . $rewardData['id'] . "/reject";
            $this->request($url, "", "post", "live_api");
            $items = $quote->getAllItems();
            foreach ($items as $item) {
                if ($item->getIsZinreloFreeProduct()) {
                    $item->delete();
                    $item->save();
                }
            }
            $quote->setRedeemRewardDiscount('');
            $quote->setRewardRulesData('');
            $quote->save();
            return true;
        }
        return false;
    }

    /**
     * Get Web Hook Url
     *
     * @return mixed
     */
    public function getLiveWebHookUrl()
    {
        return $this->getConfig(self::XML_PATH_LIVE_WEB_HOOK_URL);
    }

    /**
     * Request to zinrelo for specific event URL
     *
     * @param mixed $url
     * @param mixed $params
     * @param string $requestType
     * @param string $apiType
     * @return mixed
     */
    public function request($url, $params, $requestType = "post", $apiType = "event_api")
    {
        try {
            /* Add request data to log file*/
            if ($this->enableCustomLog()) {
                $this->logger->info("==============Start==============");
                $this->logger->info("URL: " . $url);
                $this->logger->info("RequestType: " . $requestType);
            }
            if ($apiType == "live_api") {
                $headers = [
                    "content-type" => "application/json",
                    "api-key" => $this->getApiKey(),
                    "partner-id" => $this->getPartnerId()
                ];
            } else {
                $milliseconds = round(microtime(true) * 1000);
                $nonce = $milliseconds;
                $request_body = $params;
                $secret_key = $this->getApiKey();
                $message = $request_body . ":" . $nonce;
                if ($this->enableCustomLog()) {
                    $this->logger->info("Message: " . $message);
                }
                $computed_signature = $this->generateHasHmac('sha512', $message, $secret_key);
                $headers = [
                    "content-type" => "application/json",
                    "x-magento-signature" => $computed_signature,
                    'nonce' => $nonce,
                    "partner-id" => $this->getPartnerId()
                ];
            }
            if ($this->enableCustomLog()) {
                $this->logger->info("Headers: " . json_encode($headers));
                $this->logger->info("Params: " . $params);
            }
			$curlRequest = $this->curl->create();
            $curlRequest->setHeaders($headers);
            if ($requestType == "post") {
                $curlRequest->post($url, $params);
            } else {
                if (!empty($params)) {
                    $curlRequest->get($url, $params);
                } else {
                    $curlRequest->get($url);
                }
            }
            $response = $curlRequest->getBody();
            if ($this->enableCustomLog()) {
                $this->logger->info("Response: " . $response);
                $this->logger->info("==============End===============");
            }
            return $this->returnResponseData($response);
        } catch (Exception $e) {
            $this->addErrorLog($e->getMessage());
        }
    }

    /**
     * Get Api Key
     *
     * @return mixed
     */
    public function getApiKey()
    {
        return $this->getConfig(self::XML_PATH_API_KEY) ?? '';
    }

    /**
     * Get Api Key Identifier
     *
     * @return mixed
     */
    public function getApiKeyIdentifier()
    {
        return $this->getConfig(self::XML_PATH_API_KEY_IDENTIFIER) ?? '';
    }

    /**
     * Get Partner Id
     *
     * @return mixed
     */
    public function getPartnerId()
    {
        return $this->getConfig(self::XML_PATH_PARTNER_ID) ?? '';
    }

    /**
     * Generate hash hmac key
     *
     * @param string $shaMethod
     * @param string $msgData
     * @param string $secret_key
     * @return mixed
     */
    public function generateHasHmac($shaMethod, $msgData, $secret_key)
    {
        return hash_hmac($shaMethod, $msgData, $secret_key);
    }

    /**
     * Decode json response and handle response Data
     *
     * @param mixed $responseData
     * @return array|bool[]
     */
    public function returnResponseData($responseData): array
    {
        $response = (!empty($responseData) && $this->isJson($responseData)) ?
            $this->json->unserialize($responseData) : [];
        if (!empty($response)) {
            if (!isset($response['error_code']) && !isset($response['error'])) {
                $result = [
                    "success" => true,
                    "result" => $response
                ];
            } else {
                $result = [
                    "success" => false,
                    "result" => $response['reason']
                ];
            }
        } else {
            $result = [
                "success" => false,
                "result" => __("Something went wrong, check and try again")
            ];
        }
        return $result;
    }

    /**
     * Is Json
     *
     * @param string $string
     * @return bool
     */
    public function isJson($string)
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Get quote
     *
     * @return CartInterface|Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuote()
    {
        return $this->checkoutSession->getQuote();
    }

    /**
     * Request to Zinrelo for order_create
     *
     * @param string $id
     * @param string $replacedOrderId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function createZinreloOrder($id, $replacedOrderId)
    {
        $order = $this->orderRepository->get($id);
        $couponCode = $this->getCouponCodes($order);
        $order->setCouponCode($couponCode);
        $order->getPayment()->setMethodInstance();
        $orderData = $order->debug();
        $orderData['payment'] = $order->getPayment()->debug();
        unset($orderData['payment (Magento\Sales\Model\Order\Payment\Interceptor)']);
        $orderData['payment'] = $this->setFormatedPrice($orderData['payment']);
        $orderData = $this->setFormatedPrice($orderData);
        $orderData["entity_id"] = $replacedOrderId;
        $orderData["order_id"] = $replacedOrderId;
        unset($orderData['items']);
        $totalDiscountAmount = 0;
        $totalBaseDiscountAmount = 0;
        foreach ($order->getItems() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }
            $quote = $this->quoteRepository->get($order->getQuoteId());
            if (!empty($quote->getRedeemRewardDiscount())) {
                $redeemReward = $quote->getRedeemRewardDiscount();
                $rewardData = $this->getRewardRulesData($quote, $redeemReward);
                if ($rewardData) {
                    $discountValue = $rewardData['reward_value'];
                    if ($rewardData['rule'] == 'percentage_discount') {
                        $discountAmount = $item->getDiscountAmount() +
                            (($item->getPrice() * $item->getQtyOrdered()) * $discountValue / 100);
                        $baseDiscountAmount = $item->getBaseDiscountAmount() +
                            (($item->getBasePrice() * $item->getQtyOrdered()) * $discountValue / 100);
                        $item->setDiscountAmount($discountAmount);
                        $item->setBaseDiscountAmount($baseDiscountAmount);
                        $item->save();
                        $totalDiscountAmount += $discountAmount;
                        $totalBaseDiscountAmount += $baseDiscountAmount;
                    } elseif ($rewardData['rule'] == 'fixed_amount_discount') {
                        $OrderTotal = $quote->getSubtotal();
                        $OrderBaseTotal = $quote->getBaseSubtotal();
                        $totalPercentage = ($item->getPrice() * $item->getQtyOrdered()) / $OrderTotal;
                        $totalBasePercentage = ($item->getBasePrice() * $item->getQtyOrdered()) / $OrderBaseTotal;
                        $discountAmount = $item->getDiscountAmount() + ($totalPercentage * $discountValue);
                        $baseDiscountAmount = $item->getBaseDiscountAmount() + ($totalBasePercentage * $discountValue);
                        $item->setDiscountAmount($discountAmount);
                        $item->setBaseDiscountAmount($baseDiscountAmount);
                        $item->save();
                        $totalDiscountAmount += $discountAmount;
                        $totalBaseDiscountAmount += $baseDiscountAmount;
                    }
                }
            }
            $orderItemData = $item->debug();
            $orderItemData['qty_ordered'] = (int)$orderItemData['qty_ordered'];
            if (isset($orderItemData['product_options']['info_buyRequest']['qty'])) {
                $buyRequestQty = $orderItemData['product_options']['info_buyRequest']['qty'];
                $orderItemData['product_options']['info_buyRequest']['qty'] = (int)$buyRequestQty;
            }
            if (isset($orderItemData['qty_canceled'])) {
                $orderItemData['qty_canceled'] = (int)$orderItemData['qty_canceled'];
            }
            if (isset($orderItemData['qty_invoiced'])) {
                $orderItemData['qty_invoiced'] = (int)$orderItemData['qty_invoiced'];
            }
            if (isset($orderItemData['qty_refunded'])) {
                $orderItemData['qty_refunded'] = (int)$orderItemData['qty_refunded'];
            }
            if (isset($orderItemData['qty_shipped'])) {
                $orderItemData['qty_shipped'] = (int)$orderItemData['qty_shipped'];
            }

            $orderItemData = $this->setFormatedPrice($orderItemData);

            $orderItemData['order_id'] = $replacedOrderId;
            $productId = $orderItemData['product_id'];
            $orderItemData['product_url'] = $this->getProductUrl($productId);
            $orderItemData['product_image_url'] = $this->getProductImageUrl($productId);
            $categoryData = $this->getCategoryData($productId);
            $orderItemData['category_name'] = $categoryData['name'];
            $orderItemData['category_ids'] = $categoryData['ids'];
            $orderData['items'][] = $orderItemData;
        }
        $orderData['discount_amount'] = $totalDiscountAmount;
        $orderData['base_discount_amount'] = $totalBaseDiscountAmount;
        if (isset($orderData["addresses"])) {
            $addressesData = $orderData["addresses"];
            unset($orderData["addresses"]);
            foreach ($addressesData as $key => $address) {
                $orderData["addresses"][] = $address;
            }
        }
        if (isset($orderData["status_histories"])) {
            $statusHistory = $orderData["status_histories"];
            unset($orderData["status_histories"]);
            foreach ($statusHistory as $hostory) {
                $orderData["status_histories"][] = $hostory;
            }
        }
        $orderData['total_qty_ordered'] = (int)$orderData['total_qty_ordered'];
        $params = [
            "member_id" => $order->getCustomerEmail(),
            "activity_id" => "order_create",
            "data" => $orderData
        ];
        $url = $this->getWebHookUrl();
        $params = $this->json->serialize($params);
        $event = $this->getRewardEvents();
        if (in_array('order_create', $event, true)) {
            $response = $this->request($url, $params, "post");
        }
        return true;
    }

    /**
     * Get Coupon Codes
     *
     * @param Order $order
     * @return array|string[]
     */
    public function getCouponCodes($order)
    {
        return $order->getCouponCode() ? [$order->getCouponCode()] : [];
    }

    /**
     * Set formated price and return updated array
     *
     * @param array $priceData
     * @return array|string[]
     */
    public function setFormatedPrice($priceData)
    {
        foreach ($priceData as $key => $itemData) {
            if ((strpos($key, 'price') !== false || strpos($key, 'amount') !== false ||
                    strpos($key, 'rate') !== false || strpos($key, 'incl') !== false ||
                    strpos($key, 'discount') !== false || strpos($key, 'base_shipping') !== false ||
                    strpos($key, 'base_tax') !== false || strpos($key, 'row_') !== false ||
                    strpos($key, 'shipping_invoiced') !== false || strpos($key, 'tax_invoiced') !== false ||
                    strpos($key, 'shipping_captured') !== false || strpos($key, 'total') !== false
                ) && $itemData != ""
            ) {
                $priceData[$key] = (float)$itemData;
            }
        }
        return $priceData;
    }

    /**
     * Get product url
     *
     * @param mixed $id
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getProductUrl($id)
    {
        $product = $this->productRepository->getById($id);
        return $product->getProductUrl();
    }

    /**
     * Get Product Image Url
     *
     * @param mixed $id
     * @return string
     * @throws NoSuchEntityException
     */
    public function getProductImageUrl($id)
    {
        $product = $this->productRepository->getById($id);
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl("media");
        if ($product->getImage()) {
            return $mediaUrl . 'catalog/product' . $product->getImage();
        }
        $imageData = $this->helperImageFactory->create();
        return $this->assetRepos->getUrl($imageData->getPlaceholder('small_image'));
    }

    /**
     * Get Category Data
     *
     * @param array $productId
     * @return array
     * @throws NoSuchEntityException
     */
    public function getCategoryData($productId)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $prodCatIds = $this->productCategory->getCategoryIds($productId);
        $categoryName = [];
        $categoryIds = [];
        if ($prodCatIds) {
            foreach ($prodCatIds as $catId) {
                $categoryInstance = $this->categoryRepository->get($catId, $storeId);
                $categoryName['name'][] = $categoryInstance->getName();
                $categoryIds['ids'][] = $categoryInstance->getId();
            }
        }
        return [
            'name' => implode(',', array_unique(array_unique($categoryName['name']))),
            'ids' => implode(',', array_unique(array_unique($categoryIds['ids'])))
        ];
    }

    /**
     * Get Web Hook Url
     *
     * @return mixed
     */
    public function getWebHookUrl()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_WEB_HOOK_URL);
    }

    /**
     * Save Web Hook Url
     *
     * @return mixed
     */
    public function saveWebHookUrl($webhookUrl)
    {
        $this->writeConfig->save(self::XML_PATH_WEB_HOOK_URL, $webhookUrl);
        return true;
    }

    /**
     * Create ZIF Integration
     *
     * @return mixed
     */
    public function createOrUpdateZIFIntegration($url)
    {
        try{
            $headers = [
                "content-type" => "application/json",
                "accept" => "application/json",
                'api-key' => $this->getApiKey(),
                "partner-id" => $this->getPartnerId()
            ];
            $body = [
                "integration_type" => "magento_to_zinrelo",
                "config" => [
                    "secret_key" => $this->getApiKey(),
                    "events" => $this->getRewardEvents()
                ],
                "status" => "active"
            ];

            $jsonBody = json_encode($body);
            $curlRequest = $this->curl->create();
            $curlRequest->setHeaders($headers);
            $curlRequest->post($url, $jsonBody);
            $response = $curlRequest->getBody();
            if ($this->enableCustomLog()) {
                $this->logger->info("Response: " . $response);
                $this->logger->info("=============================");
            }
            $data = json_decode($response, true);
            return $data;
        }
        catch (Exception $e) {
            $this->addErrorLog($e->getMessage());
            $error = 'Failed to create a Webhook URL. Please check the details and try again.';
            throw new Exception($error);
        }
    }


    /**
     * Get Web Hook Integration ID
     *
     * @return mixed
     */
    public function getWebHookIntegrationID()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_WEBHOOK_INTEGRATION_ID);
    }

    /**
     * Save Web Hook Integration ID
     *
     * @return mixed
     */
    public function saveWebHookIntegrationID($webhookIntegrationID)
    {
        $this->writeConfig->save(self::XML_PATH_WEBHOOK_INTEGRATION_ID, $webhookIntegrationID);
        return true;
    }

    /**
     * Get Web Hook Integration URL
     *
     * @return mixed
     */
    public function getWebHookIntegrationURL()
    {
        return $this->getConfig(self::XML_PATH_WEBHOOK_INTEGRATION_URL);
    }

    /**
     * Get Reward Events
     *
     * @return array
     */
    public function getRewardEvents()
    {
        if (!empty($this->getConfig(self::XML_PATH_REWARD_EVENTS)) && $this->isModuleEnabled()) {
            return explode(',', $this->getConfig(self::XML_PATH_REWARD_EVENTS));
        }
        return [];
    }

    /**
     * Order purchase request to zinrelo
     *
     * @param Order $order
     * @param string $replacedOrderId
     * @return bool
     */
    public function orderPurchaseRequest($order, $replacedOrderId): bool
    {
        $url = $this->getLiveWebHookUrl() . "transactions/award";
        $transactionAttributes = [
            "tags" => [
                "Order Purchased",
                "New Order"
            ],
            "reason" => "purchase",
            "order_id" => $replacedOrderId,
            "coupons" => "testCPN",
            "order_total" => $order->getGrandTotal(),
            "order_subtotal" => $order->getSubtotal(),
            "products" => $this->getOrderedItems($order->getAllItems())
        ];
        $currentTime = $this->timezoneInterface->date()->format('Y-m-d H:i:s');
        $approvalDate = date("m/d/Y H:i:s", strtotime("$currentTime + 24 hours"));
        $params = [
            "member_id" => $order->getCustomerEmail(),
            "activity_id" => "made_a_purchase",
            "transaction_attributes" => $transactionAttributes,
            "approval_date" => $approvalDate
        ];
        $params = $this->json->serialize($params);
        $this->request($url, $params, "post", "live_api");
        return true;
    }

    /**
     * Get ordered items
     *
     * @param Order $orderItems
     * @return array
     * @throws Zend_Log_Exception
     * @throws NoSuchEntityException
     */
    public function getOrderedItems($orderItems): array
    {
        $items = [];
        foreach ($orderItems as $item) {
            try {
                $product = $this->productRepository->getById($item->getProductId());
            } catch (NoSuchEntityException $e) {
                $this->addErrorLog($e->getMessage());
            }
            $mediaUrl = $this->storeManager->getStore()->getBaseUrl("media");
            $productImageUrl = $mediaUrl . 'catalog/product' . $product->getImage();
            $productUrl = $product->getProductUrl();
            $items[] = [
                'product_id' => $item->getProductId(),
                'product_quantity' => $item->getQtyOrdered(),
                'product_price' => $item->getPrice(),
                'product_category' => $this->getCategoryData($item->getProductId())['name'],
                'product_category_ids' => $this->getCategoryData($item->getProductId())['ids'],
                'product_title' => $item->getName(),
                'product_url' => $productUrl,
                'product_image_url' => $productImageUrl
            ];
        }
        return $items;
    }

    /**
     * Get Config Languages
     *
     * @return mixed
     */
    public function getConfigLanguage()
    {
        return $this->getConfig(self::XML_PATH_LANGUAGES);
    }

    /**
     * Show Redeem Reward Discount In Admin Order
     *
     * @param mixed $order
     * @throws NoSuchEntityException
     */
    public function showRedeemRewardDiscountInAdminOrder($order)
    {
        $quote = $this->quoteRepository->get($order->getQuoteId());
        $totalDiscountAmount = 0;
        $totalBaseDiscountAmount = 0;
        if (!empty($quote->getRedeemRewardDiscount())) {
            $redeemReward = $quote->getRedeemRewardDiscount();
            $rewardData = $this->getRewardRulesData($quote, $redeemReward);
            $discountValue = $rewardData['reward_value'];
            $orderItems = $this->orderItem->create();
            $orderItems->addAttributeToSelect('*')->addFieldToFilter('order_id', ['eq' => $order->getEntityId()]);
            if ($rewardData['rule'] == 'percentage_discount') {
                foreach ($orderItems as $orderItem) {
                    $discountAmount = $orderItem->getDiscountAmount() +
                        (($orderItem->getPrice() * $orderItem->getQtyOrdered()) * $discountValue / 100);
                    $baseDiscountAmount = $orderItem->getBaseDiscountAmount() +
                        (($orderItem->getBasePrice() * $orderItem->getQtyOrdered()) * $discountValue / 100);
                    $orderItem->setDiscountAmount($discountAmount);
                    $orderItem->setBaseDiscountAmount($baseDiscountAmount);
                    $orderItem->save();
                    $totalDiscountAmount += $discountAmount;
                    $totalBaseDiscountAmount += $baseDiscountAmount;
                }
            } elseif ($rewardData['rule'] == 'fixed_amount_discount') {
                foreach ($orderItems as $orderItem) {
                    $OrderTotal = $order->getSubtotal();
                    $OrderBaseTotal = $quote->getBaseSubtotal();
                    $totalPercentage = ($orderItem->getBasePrice() * $orderItem->getQtyOrdered()) / $OrderTotal;
                    $totalBasePercentage = ($orderItem->getBasePrice() * $orderItem->getQtyOrdered()) / $OrderBaseTotal;
                    $discountAmount = $orderItem->getDiscountAmount() + ($totalPercentage * $discountValue);
                    $baseDiscountAmount = $orderItem->getBaseDiscountAmount() + ($totalBasePercentage * $discountValue);
                    $orderItem->setDiscountAmount($discountAmount);
                    $orderItem->setBaseDiscountAmount($baseDiscountAmount);
                    $orderItem->save();
                    $totalDiscountAmount += $discountAmount;
                    $totalBaseDiscountAmount += $baseDiscountAmount;
                }
            }
            $order->setDiscountAmount($totalDiscountAmount);
            $order->setBaseDiscountAmount($totalBaseDiscountAmount);
            $order->save();
        }
    }
}
