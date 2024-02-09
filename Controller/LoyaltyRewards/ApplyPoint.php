<?php

namespace Zinrelo\LoyaltyRewards\Controller\LoyaltyRewards;

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
use Zinrelo\LoyaltyRewards\Block\Cart\RewardList;

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
     * @var RewardList
     */
    private $rewardList;
    /**
     * @var Session
     */
    private $customerSession;
    /**
     * @var SerializerInterface
     */
    private $serializer;

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
     * @param RewardList $rewardList
     * @param Product $product
     * @param CheckoutSession $checkoutSession
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
        RewardList $rewardList,
        Product $product,
        CheckoutSession $checkoutSession
    ) {
        $this->formKey = $formKey;
        $this->cart = $cart;
        $this->rewardList = $rewardList;
        $this->product = $product;
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->request = $request;
        $this->messageManager = $messageManager;
        $this->helper = $helper;
        $this->customerSession = $customerSession;
        $this->serializer = $serializer;
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
        $quote->setIsAbandonedCartSent(2)->save();
        $resultJson = $this->jsonFactory->create();
        if ($redeemReward == 'cancel') {
            $rewardData = $this->helper->getRewardRulesData($quote, "");
            $this->helper->sendRejectRewardRequest($quote);
            $this->messageManager->addNotice(
                __('The redeemed %1 is canceled successfully.', $rewardData['reward_name'])
            );
            return $resultJson->setData([
                'success' => true
            ]);
        }
        $rewardRules = $this->rewardList->getRedeemRules();
        if (!empty($rewardRules)) {
            $quote->setRewardRulesData($this->helper->json->serialize($rewardRules));
            $quote->save();
        }
        $rewardData = $this->helper->getRewardRulesData($quote, $redeemReward);
        $customerId = $this->customerSession->getCustomer()->getId();

        if ($rewardData["rule"] === "product_redemption" && !empty($rewardData["product_id"])) {
            $productId = $rewardData["product_id"];
            $product = $this->product->load($productId);
            if (!$product->getEntityId() || $product->getStatus() != Status::STATUS_ENABLED) {
                $this->unsetRewardRules($quote);
                $this->messageManager->addError(__("Product that you are trying to add is not available."));
                return $resultJson->setData([
                    'success' => false
                ]);
            }
        }

        $customerEmail = $this->helper->getCustomerEmailById($customerId);
        $url = $this->helper->getLiveWebHookUrl() . "transactions/redeem";
        $params = [
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
        $params = $this->helper->json->serialize($params);

        $response = $this->helper->request($url, $params, "post", "live_api");
        if ($response["success"] && !empty($response["result"]["data"])) {
            $responseData = $response["result"]["data"];
            if ($responseData["status"] === "pending") {
                if ($rewardData["rule"] === "product_redemption" && !empty($rewardData["product_id"])) {
                    $productId = $rewardData["product_id"];
                    $additionalOptions[] = [
                        'label' =>  __("Product Redemption"),
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
                }
                $allRewardRules = $this->helper->json->unserialize($quote->getRewardRulesData());
                $allRewardRules[$responseData["reward_info"]["reward_id"]]["id"] = $responseData["id"];
                $encodedRule = $this->helper->json->serialize($allRewardRules);
                $quote->setRewardRulesData($encodedRule);
                $quote->setRedeemRewardDiscount($redeemReward);
                $quote->save();
                $this->messageManager->addSuccess(__('You have redeemed %1 successfully.', $rewardData['reward_name']));
                return $resultJson->setData([
                    'success' => true
                ]);
            } else {
                $this->unsetRewardRules($quote);
                $this->messageManager->addError(
                    __("This reward rule can not be redeemed, try with another reward rule")
                );
                return $resultJson->setData([
                    'success' => false
                ]);
            }
        } else {
            $this->unsetRewardRules($quote);
            $this->messageManager->addError(__("This reward rule can not be redeemed, try with another reward rule"));
            return $resultJson->setData([
                'success' => false
            ]);
        }
    }

    /**
     * Delete reward relus from quote when getting error from response
     *
     * @param Quote $quote
     */
    public function unsetRewardRules($quote)
    {
        $quote->setRewardRulesData('');
        $quote->save();
    }
}
