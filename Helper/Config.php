<?php

namespace Zinrelo\LoyaltyRewards\Helper;

use Exception;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\ImageFactory;
use Magento\Catalog\Model\ProductCategoryList;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;
use Magento\Customer\Model\Customer as CustomerModel;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
use Magento\Framework\View\Asset\Repository;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Zinrelo\LoyaltyRewards\Logger\Logger as ZinreloLogger;
use Zinrelo\LoyaltyRewards\Model\ZinreloEavAttributeFactory;
use Zinrelo\LoyaltyRewards\Model\ZinreloQuoteFactory;
use Zinrelo\LoyaltyRewards\Model\ZinreloQuoteItemFactory;
use Zinrelo\LoyaltyRewards\Model\ZinreloReviewFactory;
use Zinrelo\LoyaltyRewards\Model\ZinreloSalesOrderFactory;

class Config extends AbstractHelper
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
    /**
     * Cookie life time
     */
    public const COOKIE_LIFE = 300;
    /**
     * Name of Cookie that holds private content version
     */
    public const COOKIE_NAME = 'zinrelo';

    /**
     * @var Json
     */
    public $json;
    /**
     * @var ZinreloLogger
     */
    public $logger;
    /**
     * @var CartRepositoryInterface
     */
    public $quoteRepository;
    /**
     * @var ScopeConfigInterface $scopeConfig
     */
    protected $scopeConfig;
    /**
     * @var WriterInterface $writeConfig
     */
    protected $writeConfig;
    /**
     * @var Session
     */
    protected $customerSession;
    /**
     * @var Curl
     */
    protected $curl;
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;
    /**
     * @var TimezoneInterface
     */
    protected $timezoneInterface;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var ProductCategoryList
     */
    protected $productCategory;
    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;
    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;
    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;
    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;
    /**
     * @var RequestInterface
     */
    protected $request;
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;
    /**
     * @var Repository
     */
    protected $assetRepos;
    /**
     * @var ImageFactory
     */
    protected $helperImageFactory;
    /**
     * @var Escaper
     */
    protected $escaper;
    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;
    /**
     * @var CookieMetadataFactory
     */
    private $cookieMetadataFactory;
    /**
     * @var SessionManagerInterface
     */
    private $sessionManager;
    /**
     * @var ZinreloEavAttributeFactory
     */
    public $zinreloEavAttributeFactory;
    /**
     * @var ZinreloQuoteFactory
     */
    public $zinreloQuoteFactory;
    /**
     * @var ZinreloQuoteItemFactory
     */
    public $zinreloQuoteItemFactory;
    /**
     * @var ZinreloReviewFactory
     */
    public $zinreloReviewFactory;
    /**
     * @var ZinreloSalesOrderFactory
     */
    public $zinreloSalesOrderFactory;
    /**
     * @var Attribute
     */
    public $eavAttribute;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param ZinreloLogger $logger
     * @param Curl $curl
     * @param RequestInterface $request
     * @param ProductCategoryList $productCategory
     * @param CustomerRepositoryInterface $customerRepository
     * @param TimezoneInterface $timezoneInterface
     * @param ProductRepositoryInterface $productRepository
     * @param Session $customerSession
     * @param CheckoutSession $checkoutSession
     * @param QuoteFactory $quoteFactory
     * @param Repository $assetRepos
     * @param Escaper $escaper
     * @param ImageFactory $helperImageFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param CategoryRepositoryInterface $categoryRepository
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $writeConfig
     * @param Json $json
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param SessionManagerInterface $sessionManager
     * @param ZinreloEavAttributeFactory $zinreloEavAttributeFactory
     * @param ZinreloQuoteFactory $zinreloQuoteFactory
     * @param ZinreloQuoteItemFactory $zinreloQuoteItemFactory
     * @param ZinreloReviewFactory $zinreloReviewFactory
     * @param ZinreloSalesOrderFactory $zinreloSalesOrderFactory
     * @param Attribute $eavAttribute
     */
    public function __construct(
        Context $context,
        ZinreloLogger $logger,
        Curl $curl,
        RequestInterface $request,
        ProductCategoryList $productCategory,
        CustomerRepositoryInterface $customerRepository,
        TimezoneInterface $timezoneInterface,
        ProductRepositoryInterface $productRepository,
        Session $customerSession,
        CheckoutSession $checkoutSession,
        QuoteFactory $quoteFactory,
        Repository $assetRepos,
        Escaper $escaper,
        ImageFactory $helperImageFactory,
        OrderRepositoryInterface $orderRepository,
        CategoryRepositoryInterface $categoryRepository,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        WriterInterface $writeConfig,
        Json $json,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        SessionManagerInterface $sessionManager,
        CartRepositoryInterface $quoteRepository,
        ZinreloEavAttributeFactory $zinreloEavAttributeFactory,
        ZinreloQuoteFactory $zinreloQuoteFactory,
        ZinreloQuoteItemFactory $zinreloQuoteItemFactory,
        ZinreloReviewFactory $zinreloReviewFactory,
        ZinreloSalesOrderFactory $zinreloSalesOrderFactory,
        Attribute $eavAttribute
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
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->sessionManager = $sessionManager;
        $this->zinreloEavAttributeFactory = $zinreloEavAttributeFactory;
        $this->zinreloQuoteFactory = $zinreloQuoteFactory;
        $this->zinreloQuoteItemFactory = $zinreloQuoteItemFactory;
        $this->zinreloReviewFactory = $zinreloReviewFactory;
        $this->zinreloSalesOrderFactory = $zinreloSalesOrderFactory;
        $this->quoteRepository = $quoteRepository;
        $this->eavAttribute = $eavAttribute;
        parent::__construct($context);
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
     * Get Config
     *
     * @param mixed $config_path
     * @return mixed
     * @throws NoSuchEntityException
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
     * Save Config
     *
     * @return mixed
     */
    public function saveConfig($config_path, $config_value)
    {
        return $this->writeConfig->save(
            $config_path,
            $config_value
        );
    }
    
    /**
     * Web Hook Url, which is received from Zinrelo to sent API resquest
     *
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getWebHookUrl()
    {
        return $this->getConfig(self::XML_PATH_WEB_HOOK_URL);
    }

    /**
     * Save Web Hook Url
     *
     * @return mixed
     */
    public function saveWebHookUrl($webhookUrl)
    {
        $this->saveConfig(self::XML_PATH_WEB_HOOK_URL, $webhookUrl);
        return true;
    }

    /**
     * Create ZIF Integration
     *
     * @return mixed
     */
    public function createOrUpdateZIFIntegration($url)
    {
        $headers = [
            "content-type" => "application/json",
            "accept" => "application/json",
            'api-key' => $this->getApiKey(),
            "partner-id" => $this->getPartnerId()
        ];
        $events = $this->getRewardEvents();
        if (in_array('order_refund', $events)) {
                $events = array_diff($events, ['order_refund']);
                $events[] = 'partial_order_refund';
                $events[] = 'full_order_refund';
        }

        $body = [
            "integration_type" => "magento_to_zinrelo",
            "config" => [
                "secret_key" => $this->getApiKey(),
                "events" => array_values($events)
            ],
            "status" => "active"
        ];
        $jsonBody = json_encode($body);
        $this->curl->setHeaders($headers);
        $this->curl->post($url, $jsonBody);
        $response = $this->curl->getBody();
        $responseCode = $this->curl->getStatus();
        $data = json_decode($response, true);
        $this->logger->info("==============Start==============");
        $this->logger->info("URL: " . $url);
        $this->logger->info("RequestType: " . "post");
        $this->logger->info("Headers: " . json_encode($headers));
        $this->logger->info("Response: " . $response);
        $this->logger->info("==============End===============");
        if ($responseCode === 200) {
            return $data;
        }
        else{
            $error = 'Failed to create a Webhook URL. Please check the configuration details and try again.';
            $this->saveWebHookUrl(NULL);
            $this->saveWebHookIntegrationID(NULL);
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
        return $this->getConfig(self::XML_PATH_WEBHOOK_INTEGRATION_ID);
    }

    /**
     * Save Web Hook Integration ID
     *
     * @return mixed
     */
    public function saveWebHookIntegrationID($webhookIntegrationID)
    {
        $this->saveConfig(self::XML_PATH_WEBHOOK_INTEGRATION_ID, $webhookIntegrationID);
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
     * Json Serialize
     *
     * @param mixed $data
     * @return bool|false|string
     */
    public function jsonSerialize($data)
    {
        try {
            return $this->json->serialize($data);
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }

    /**
     * Json UnSerialize
     *
     * @param mixed $data
     * @return bool|false|string|array
     */
    public function jsonUnSerialize($data)
    {
        try {
            return $this->json->serialize($data);
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }
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
     * Get Customer Email By Checkout
     *
     * @return string
     */
    public function getCustomerEmailByCheckout()
    {
        return $this->checkoutSession->getCustomer()->getEmail() ?? '';
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
     * Get Applied Rule Data
     *
     * @param mixed $quote
     * @param string $ruleId
     * @return mixed
     */
    public function getRewardRulesData($quote, $ruleId = "")
    {
        $zinreloQuote = $this->getZinreloQuoteByQuoteId($quote->getId());
        if ($ruleId == "" && $zinreloQuote) {
            $ruleId = !$zinreloQuote->isEmpty() ? $zinreloQuote->getRedeemRewardDiscount() : '';
        }

        if (!$zinreloQuote->isEmpty()) {
            if ($zinreloQuote->getRewardRulesData()) {
                $rewardRules = $this->json->unserialize($zinreloQuote->getRewardRulesData());
                return $rewardRules[$ruleId] ?? [];
            }
        }
        return [];
    }

    /**
     * Get reward point rules
     *
     * @return array
     */
    public function getRedeemRules()
    {
        $customerEmail = $this->getCustomerEmailBySession();
        $url = $this->getLiveWebHookUrl() . "members/" . $customerEmail . "/rewards";
        $url = $this->getIdParam($url);
        $response = $this->request($url, "", "get", "live_api");
        $rewardRules = [];
        if ($response && $response["success"] && !empty($response["result"]["data"]["rewards"])) {
            $rules = $response["result"]["data"]["rewards"];
            $rewardTypes = $this->getDefaultRewardTypes();
            $availablePoint = $this->getRewardPoints();
            $quote = $this->getQuote();
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
     * Get Customer Email By Session
     *
     * @return string
     */
    public function getCustomerEmailBySession()
    {
        return $this->customerSession->getCustomer()->getEmail() ?? '';
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
     * Get Web Hook Url
     *
     * @return mixed
     */
    public function getLiveWebHookUrl()
    {
        return $this->getConfig(self::XML_PATH_LIVE_WEB_HOOK_URL);
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
            $message = '';
            /* Add request data to log file*/
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
                $computed_signature = $this->generateHasHmac('sha512', $message, $secret_key);
                $headers = [
                    "content-type" => "application/json",
                    "x-magento-signature" => $computed_signature,
                    'nonce' => $nonce,
                    "partner-id" => $this->getPartnerId()
                ];
            }
            $this->curl->setHeaders($headers);
            if ($requestType == "post") {
                $this->curl->post($url, $params);
            } else {
                if (!empty($params)) {
                    $this->curl->get($url, $params);
                } else {
                    $this->curl->get($url);
                }
            }
            $response = $this->curl->getBody();
            $this->logger->loggedAsInfoData($url, $requestType, $message, $headers, $params, $response);
            return $this->returnResponseData($response);
        } catch (Exception $e) {
            $this->logger->addErrorLog($e->getMessage());
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

    /**
     * Get available reward points
     *
     * @return mixed
     */
    public function getRewardPoints()
    {
        try {
            $customerEmail = $this->getCustomerEmailBySession();
            $url = $this->getLiveWebHookUrl() . "members/" . $customerEmail;
            $url = $this->getIdParam($url);
            $response = $this->request($url, "", "get", "live_api");
            if ($response) {
                if ($response["success"] && !empty($response["result"]["data"]["available_points"])) {
                    $point = $response["result"]["data"]["available_points"];
                    return $point > 0 ? $point : "error";
                } else {
                    return "error";
                }
            }
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
            return "error";
        }
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
     * Get data from cookie set in remote address
     *
     * @param mixed $name
     * @return value
     */
    public function getCookie($name)
    {
        return $this->cookieManager->getCookie($name);
    }

    /**
     * Set data to cookie in remote address
     *
     * @param mixed $value
     * @param int $duration
     * @throws CookieSizeLimitReachedException
     * @throws FailureToSendException
     * @throws InputException
     */
    public function setCookie($value, $duration = 300)
    {
        $metadata = $this->cookieMetadataFactory
            ->createPublicCookieMetadata()
            ->setDuration($duration)
            ->setPath($this->sessionManager->getCookiePath())
            ->setDomain($this->sessionManager->getCookieDomain());

        $this->cookieManager->setPublicCookie(self::COOKIE_NAME, $value, $metadata);
    }

    /**
     * Delete cookie remote address
     *
     * @return void
     * @throws FailureToSendException
     * @throws InputException
     */
    public function deleteCookie()
    {
        $this->cookieManager->deleteCookie(
            self::COOKIE_NAME,
            $this->cookieMetadataFactory
                ->createCookieMetadata()
                ->setPath($this->sessionManager->getCookiePath())
                ->setDomain($this->sessionManager->getCookieDomain())
        );
    }

    /*Managed to set zinrelo quote, quoteItem, review, sales related data to custom table*/

    /**
     * Get Zinrelo quote specific Data using QuoteId
     *
     * @param int $quoteId
     */
    public function getZinreloQuoteByQuoteId($quoteId)
    {
        return $this->zinreloQuoteFactory->create()
            ->getCollection()
            ->addFieldToFilter('quote_id', $quoteId)
            ->getFirstItem();
    }

    /**
     * Get Zinrelo quote item specific Data using itemId
     *
     * @param int $itemId
     */
    public function getZinreloQuoteItemByItemId($itemId)
    {
        return $this->zinreloQuoteItemFactory->create()
            ->getCollection()
            ->addFieldToFilter('quote_item_id', $itemId)
            ->getFirstItem();
    }

    /**
     * Set zinrelo abandoned cart sent status
     *
     * @param int $quoteId
     * @param int $value
     */
    public function setAbandonedCartSent($quoteId, $value)
    {
        $zinreloQuote = $this->getZinreloQuoteByQuoteId($quoteId);
        if (!$zinreloQuote->isEmpty()) {
            $zinreloQuote->setIsAbandonedCartSent($value)->save();
        } else {
            $zinreloQuote->setIsAbandonedCartSent($value);
            $zinreloQuote->setQuoteId($quoteId);
            $zinreloQuote->save();
        }
    }

    /**
     * Get Zinrelo Order item specific Data using orderID
     *
     * @param int $orderId
     */
    public function getZinreloOrderByOrderId($orderId)
    {
        return $this->zinreloSalesOrderFactory->create()
            ->getCollection()
            ->addFieldToFilter('order_id', $orderId)
            ->getFirstItem();
    }

    /**
     * Get Zinrelo product review using reviewId
     *
     * @param int $reviewId
     */
    public function getZinreloReviewByReviewId($reviewId)
    {
        return $this->zinreloReviewFactory->create()
            ->getCollection()
            ->addFieldToFilter('review_id', $reviewId)
            ->getFirstItem();
    }

    /**
     * Get Zinrelo attribute related data using attribute Id
     *
     * @param int $attributeId
     */
    public function getZinreloAttributeByAttributeId($attributeId)
    {
        return $this->zinreloEavAttributeFactory->create()
            ->getCollection()
            ->addFieldToFilter('attribute_id', $attributeId)
            ->getFirstItem();
    }

    /**
     * Get customer attribute Id using attribute code
     *
     * @param string $attributeCode
     */
    public function getCustomerAttributeId($attributeCode)
    {
        return $this->eavAttribute->getIdByCode(CustomerModel::ENTITY, $attributeCode);
    }
}
