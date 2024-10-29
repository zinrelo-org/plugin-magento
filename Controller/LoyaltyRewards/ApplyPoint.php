<?php

namespace Zinrelo\LoyaltyRewards\Controller\LoyaltyRewards;

use Exception;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Model\Quote;
use Zinrelo\LoyaltyRewards\Helper\Data;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Checkout\Model\Cart;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Zinrelo\LoyaltyRewards\Logger\Logger;
use Zinrelo\LoyaltyRewards\Model\ZinreloQuote;

class ApplyPoint implements HttpPostActionInterface
{
    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;
    /**
     * @var Data
     */
    private $helper;
    /**
     * @var ManagerInterface
     */
    private $messageManager;
    /**
     * @var FormKey
     */
    private $formKey;
    /**
     * @var Cart
     */
    private $cart;
    /**
     * @var Product
     */
    private $product;
    /**
     * @var Session
     */
    private $customerSession;
    /**
     * @var SerializerInterface
     */
    private $serializer;
    /**
     * @var Logger
     */
    private $logger;

    /**
     * ApplyPoint construct
     *
     * @param JsonFactory $jsonFactory
     * @param RequestInterface $request
     * @param Data $helper
     * @param ManagerInterface $messageManager
     * @param FormKey $formKey
     * @param Session $customerSession
     * @param Cart $cart
     * @param SerializerInterface $serializer
     * @param Product $product
     * @param CheckoutSession $checkoutSession
     * @param Logger $logger
     */
    public function __construct(
        JsonFactory $jsonFactory,
        RequestInterface $request,
        Data $helper,
        ManagerInterface $messageManager,
        FormKey $formKey,
        Session $customerSession,
        Cart $cart,
        SerializerInterface $serializer,
        Product $product,
        CheckoutSession $checkoutSession,
        Logger $logger
    ) {
        $this->formKey = $formKey;
        $this->cart = $cart;
        $this->product = $product;
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->request = $request;
        $this->messageManager = $messageManager;
        $this->helper = $helper;
        $this->customerSession = $customerSession;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * Apply redeem point rule and save in quote
     *
     * @return ResponseInterface|ResultInterface|mixed
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $redeemReward = $this->request->getPost('redeem_reward');
        $quote = $this->checkoutSession->getQuote();
        /*Managed Set zinrelo quote related to Data to custom table*/
        $this->helper->setAbandonedCartSent($quote->getId(), 2);
        $zinreloQuote = $this->helper->getZinreloQuoteByQuoteId($quote->getId());
        /*End*/
        $resultJson = $this->jsonFactory->create();
        $this->cart->setUpdatedAt()->save();
        if ($redeemReward == 'cancel') {
            $rewardData = $this->helper->getRewardRulesData($quote);
            $this->helper->sendRejectRewardRequest($quote);
            $this->messageManager->addNotice(
                __('The redeemed %1 is canceled successfully.', $rewardData['reward_name'])
            );
            return $resultJson->setData([
                'success' => true
            ]);
        }
        $rewardRules = $this->helper->getRedeemRules();
        if (!empty($rewardRules)) {
            try {
                $zinreloQuote->setRewardRulesData($this->helper->jsonSerialize($rewardRules));
                $zinreloQuote->save();
            } catch (Exception $e) {
                $this->logger->critical($e->getMessage());
            }
        }
        $rewardData = $this->helper->getRewardRulesData($quote, $redeemReward);
        if ($rewardData["rule"] === "product_redemption" && !empty($rewardData["product_id"])) {
            $productId = $rewardData["product_id"];
            $product = $this->product->load($productId);
            if (!$product->getEntityId() || $product->getStatus() != Status::STATUS_ENABLED) {
                $this->unsetRewardRules($zinreloQuote);
                $this->messageManager->addError(__("Product that you are trying to add is not available."));
                return $resultJson->setData([
                    'success' => false
                ]);
            }
        }
        $response = $this->getApiResponse($rewardData);

        if ($response["success"] && !empty($response["result"]["data"])) {
            $responseData = $response["result"]["data"];
            if ($responseData["status"] === "pending") {
                $this->saveQuoteData($zinreloQuote, $responseData, $redeemReward, $rewardData);
                $this->messageManager->addSuccess(__('You have redeemed %1 successfully.', $rewardData['reward_name']));
                return $resultJson->setData([
                    'success' => true
                ]);
            } else {
                $this->unsetRewardRules($zinreloQuote);
                $this->messageManager->addError(
                    __("This reward rule can not be redeemed, try with another reward rule")
                );
                return $resultJson->setData([
                    'success' => false
                ]);
            }
        } else {
            $this->unsetRewardRules($zinreloQuote);
            $this->messageManager->addError(__("This reward rule can not be redeemed, try with another reward rule"));
            return $resultJson->setData([
                'success' => false
            ]);
        }
    }

    /**
     * Delete reward relus from quote when getting error from response
     *
     * @param ZinreloQuote $zinreloQuote
     */
    public function unsetRewardRules($zinreloQuote)
    {
        try {
            $zinreloQuote->setRewardRulesData('');
            $zinreloQuote->save();
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }

    /**
     * Get Api Response
     *
     * @param mixed $rewardData
     * @return mixed
     */
    public function getApiResponse($rewardData)
    {
        $url = $this->helper->getLiveWebHookUrl() . "transactions/redeem";
        $paramsData = $this->getParamsData($rewardData);
        $params = $this->helper->jsonSerialize($paramsData);
        return $this->helper->request($url, $params, "post", "live_api");
    }

    /**
     * Get Params Data
     *
     * @param mixed $rewardData
     * @return array
     */
    public function getParamsData($rewardData)
    {
        $customerEmail = $this->customerSession->getCustomer()->getEmail();
        return [
            "member_id" => $customerEmail,
            "reward_id" => $rewardData["reward_id"],
            "transaction_attributes" => [
                "reason" => "redeem",
                "tags" => [
                    "purchasenmade",
                    "redeemingpoints"
                ]
            ],
            "status" => "pending"
        ];
    }

    /**
     * Save Quote Data
     *
     * @param ZinreloQuote $zinreloQuote
     * @param mixed $responseData
     * @param mixed $redeemReward
     * @param mixed $rewardData
     */
    public function saveQuoteData($zinreloQuote, $responseData, $redeemReward, $rewardData)
    {
        try {
            if ($rewardData["rule"] === "product_redemption" && !empty($rewardData["product_id"])) {
                $this->addToCartProductWithNewPrice($rewardData);
            }
            $allRewardRules = $this->helper->json->unserialize($zinreloQuote->getRewardRulesData());
            $allRewardRules[$responseData["reward_info"]["reward_id"]]["id"] = $responseData["id"];
            $encodedRule = $this->helper->json->serialize($allRewardRules);
            $zinreloQuote->setRewardRulesData($encodedRule);
            $zinreloQuote->setRedeemRewardDiscount($redeemReward);
            $zinreloQuote->save();
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }

    /**
     * Add To Cart Product With New Price
     *
     * @param mixed $rewardData
     */
    public function addToCartProductWithNewPrice($rewardData)
    {
        try {
            $productId = $rewardData["product_id"];
            $additionalOptions[] = [
                'label' => __("Product Redemption"),
                'value' => $rewardData['reward_name']
            ];
            $params = [
                'form_key' => $this->formKey->getFormKey(),
                'product' => $productId,
                'qty' => 1
            ];
            $product = $this->product->load($productId);
            $product->setPrice(0);
            $product->addCustomOption('additional_options', $this->serializer->serialize($additionalOptions));
            $this->cart->addProduct($product, $params);
            $this->cart->save();
            /*Set free product to Zinrelo quote item*/
            $quoteItemCollection = $this->cart->getItems();
            foreach ($quoteItemCollection as $item) {
                if($item->getProductId() == $productId && $item->getPrice() == 0) {
                    $zinreloQuoteItem = $this->helper->getZinreloQuoteItemByItemId($item->getId());
                    $zinreloQuoteItem->setIsZinreloFreeProduct(1)->setQuoteItemId($item->getId())->save();
                    break;
                }
            }
        } catch (Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }
}
