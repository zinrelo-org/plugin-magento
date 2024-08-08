<?php

namespace Zinrelo\LoyaltyRewards\Controller\LoyaltyRewards;

use Firebase\JWT\JWT;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\SessionFactory;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Result\Layout;
use Zinrelo\LoyaltyRewards\Helper\Data;

class Index implements HttpPostActionInterface
{
    /**
     * @var ResultFactory
     */
    private $resultFactory;
    /**
     * @var JWT
     */
    private $jwt;
    /**
     * @var CustomerFactory
     */
    private $customerFactory;
    /**
     * @var SerializerInterface
     */
    private $serializer;
    /**
     * @var SessionFactory
     */
    private $sessionFactory;
    /**
     * @var CountryFactory
     */
    private $countryFactory;
    /**
     * @var Resolver
     */
    private $store;
    /**
     * @var Data
     */
    private $helper;

    /**
     * Index constructor.
     *
     * @param ResultFactory $resultFactory
     * @param JWT $jwt
     * @param CustomerFactory $customerFactory
     * @param SerializerInterface $serializer
     * @param SessionFactory $sessionFactory
     * @param CountryFactory $countryFactory
     * @param Resolver $store
     * @param Data $helper
     */
    public function __construct(
        ResultFactory $resultFactory,
        JWT $jwt,
        CustomerFactory $customerFactory,
        SerializerInterface $serializer,
        SessionFactory $sessionFactory,
        CountryFactory $countryFactory,
        Resolver $store,
        Data $helper
    ) {
        $this->resultFactory = $resultFactory;
        $this->jwt = $jwt;
        $this->customerFactory = $customerFactory;
        $this->serializer = $serializer;
        $this->sessionFactory = $sessionFactory;
        $this->countryFactory = $countryFactory;
        $this->store = $store;
        $this->helper = $helper;
    }

    /**
     * Execute
     *
     * @return ResponseInterface|ResultInterface|Layout
     */
    public function execute()
    {
        $key = $this->helper->getApiKey();
        $partnerId = $this->helper->getPartnerId();
        $apiKeyIdentifier = $this->helper->getApiKeyIdentifier();
        $customerEmail = "";
        $customerFirstName = "";
        $customerLastName = "";
        $customerBirthDate = "";
        $street = [];
        $city = "";
        $region = "";
        $postcode = "";
        $country = "";
        $telephone = "";
        $lang = "";
        $isSetCookies = false;

        $jsonConfigLanguage = $this->helper->getConfigLanguage();
        if ($jsonConfigLanguage) {
            $lang = $this->store->getLocale() ?? "";
            $configLanguage = $this->serializer->unserialize($jsonConfigLanguage);
            $config = stristr($lang, "_", true);
            if (isset($configLanguage[$config]) && $configLanguage[$config]) {
                $lang = $configLanguage[$config];
            } else {
                $lang = "";
            }
        }
        $customerId = $this->sessionFactory->create()->getCustomerId();
        $customer = $this->customerFactory->create()->load($customerId);
        if ($customer->getEntityId()) {
            $billingAddress = $customer->getDefaultBillingAddress() ?
                $customer->getDefaultBillingAddress()->getData() : [];
            $customerEmail = $customer->getEmail();
            $customerFirstName = $customer->getFirstname();
            $customerLastName = $customer->getLastname();
            $customerBirthDate = $customer->getDob();
            $telephone = $billingAddress['telephone'] ?? "";
            $city = $billingAddress['city'] ?? "";
            $region = $billingAddress['region'] ?? "";
            $postcode = $billingAddress['postcode'] ?? "";
            $street = isset($billingAddress['street']) ? explode("\n", $billingAddress['street']) : [];
            $country = isset($billingAddress['country_id']) ? $this->getCountryName($billingAddress['country_id']) : "";
        }

        $payload = [
            'member_id' => $customerEmail,
            'sub' => $apiKeyIdentifier,
            'email_address' => $customerEmail,
            'first_name' => $customerFirstName,
            'last_name' => $customerLastName,
            'phone_number' => $telephone ? (preg_match('/^\+[0-9]{2}-[0-9]{10}+$/', $telephone) ? $telephone : "") : "",
            'birthdate' => $customerBirthDate,
            'preferred_language' => $lang,
            'address' => [
                'line1' => $street[0] ?? "",
                'line2' => $street[1] ?? ($street[0] ?? ""),
                'city' => $city,
                'state' => $region,
                'country' => $country,
                'postal_code' => $postcode,
            ],
            'exp' => round(microtime(true) * 1000)
        ];

        $data = $this->jwt->encode($payload, $key, 'HS256');
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        if ($customerEmail) {
            $this->helper->setCookie($data);
            $isSetCookies = true;
        }
        $resultJson->setData(
            [
                'tokenData' => $data,
                'payload' => $payload,
                'partnerId' => $partnerId,
                'isSetCookies' => $isSetCookies
            ]
        );
        return $resultJson;
    }

    /**
     * Get Country Name
     *
     * @param string $countryCode
     * @return string
     */
    public function getCountryName($countryCode)
    {
        $country = $this->countryFactory->create()->loadByCode($countryCode);
        return $country->getName();
    }
}
