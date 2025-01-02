<?php

namespace Zinrelo\LoyaltyRewards\Helper;

use Magento\Customer\Api\Data\CustomerInterface;

class Data extends Config
{
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
        /*This condition only true when $isset passed as false.
        We have passed false when it needed else this condition will not true and order will not nested save*/
        if (!$isset) {
            $zinreloOrder = $this->getZinreloOrderByOrderId($orderId);
            $zinreloOrder->setZinreloReward("{}")->setOrderId($orderId);
            $zinreloOrder->save();
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
        $zinreloQuote = $this->getZinreloQuoteByQuoteId($quote->getId());
        $redeemReward = $zinreloQuote->getRedeemRewardDiscount();
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
     * Get Customer Email By Id
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
                return $this->getCustomerById($customerId)->getEmail();
            } catch (Exception $e) {
                $this->logger->addErrorLog($e->getMessage());
            }
        }
        return "";
    }

    /**
     * Get Customer By Id
     *
     * @param mixed $customerId
     * @return CustomerInterface|string
     */
    public function getCustomerById($customerId)
    {
        if ($customerId) {
            try {
                return $this->customerRepository->getById($customerId);
            } catch (Exception $e) {
                $this->logger->addErrorLog($e->getMessage());
            }
        }
        return "";
    }

    /**
     * Send Reject reward point API request to Zinrelo when cart gone Abandoned or Redeem Cancel button clicked
     *
     * @param Quote $quote
     * @return bool
     */
    public function sendRejectRewardRequest($quote)
    {
        try {
            $zinreloQuote = $this->getZinreloQuoteByQuoteId($quote->getId());
            $redeemReward = $zinreloQuote->getRedeemRewardDiscount();
            $rewardData = $this->getRewardRulesData($quote, $redeemReward);
            if (!empty($rewardData)) {
                $url = $this->getLiveWebHookUrl() . "transactions/" . $rewardData['id'] . "/reject";
                $this->request($url, "", "post", "live_api");
                $items = $quote->getAllItems();
                foreach ($items as $item) {
                    $zinreloQuoteItem = $this->getZinreloQuoteItemByItemId($item->getId());
                    if ($zinreloQuoteItem->getIsZinreloFreeProduct()) {
                        $item->delete();
                        $item->save();
                        break;
                    }
                }
                $zinreloQuote->setRedeemRewardDiscount('');
                $zinreloQuote->setRewardRulesData('');
                $zinreloQuote->save();
                return true;
            }
        } catch (Exception $e) {
            $this->logger->addErrorLog($e->getMessage());
        }
        return false;
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
        $orderData['base_discount_amount'] = abs($orderData['base_discount_amount']);
        $orderData['discount_amount'] = abs($orderData['discount_amount']);
        unset($orderData['items']);
        $totalDiscountAmount = 0;
        $totalBaseDiscountAmount = 0;
        foreach ($order->getItems() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }
            $totalDiscountAmount = $item->getDiscountAmount();
            $totalBaseDiscountAmount = $item->getBaseDiscountAmount();
            $quote = $this->quoteRepository->get($order->getQuoteId());
            $zinreloQuote = $this->getZinreloQuoteByQuoteId($order->getQuoteId());
            if (!empty($zinreloQuote->getRedeemRewardDiscount())) {
                $redeemReward = $zinreloQuote->getRedeemRewardDiscount();
                $rewardData = $this->getRewardRulesData($quote, $redeemReward);
                if ($rewardData) {
                    $discountValue = $rewardData['reward_value'];
                    if ($rewardData['rule'] == 'percentage_discount') {
                        $discountAmount += (($item->getPrice() * $item->getQtyOrdered()) * $discountValue / 100);
                        $baseDiscountAmount += (($item->getBasePrice() * $item->getQtyOrdered()) * $discountValue / 100);

                        $discountAmountFormattedNumber = number_format($discountAmount, 2);
                        $baseDiscountAmountFormattedNumber = number_format($baseDiscountAmount, 2);
                        $item->setDiscountAmount($discountAmountFormattedNumber);
                        $item->setBaseDiscountAmount($baseDiscountAmountFormattedNumber);
                        $item->save();
                        $totalDiscountAmount += $discountAmount;
                        $totalBaseDiscountAmount += $baseDiscountAmount;
                    } elseif ($rewardData['rule'] == 'fixed_amount_discount') {
                        $OrderTotal = $quote->getSubtotal();
                        $OrderBaseTotal = $quote->getBaseSubtotal();
                        $totalPercentage = ($item->getPrice() * $item->getQtyOrdered()) / $OrderTotal;
                        $totalBasePercentage = ($item->getBasePrice() * $item->getQtyOrdered()) / $OrderBaseTotal;
                        $discountAmount += ($totalPercentage * $discountValue);
                        $baseDiscountAmount = ($totalBasePercentage * $discountValue);
                        $discountAmountFormattedNumber = number_format($discountAmount, 2);
                        $baseDiscountAmountFormattedNumber = number_format($baseDiscountAmount, 2);
                        $item->setDiscountAmount($discountAmountFormattedNumber);
                        $item->setBaseDiscountAmount($baseDiscountAmountFormattedNumber);
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
     * Get product url and Image Url
     *
     * @param mixed $id
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function getProductUrlAndImageUrl($id)
    {
        $product = $this->productRepository->getById($id);
        return [
            'product_url' => $product->getProductUrl(),
            'product_image_url' => $this->getImageFullUrl($product)
        ];
    }

    /**
     * Get Image Full Url
     *
     * @param mixed $product
     * @return string
     * @throws NoSuchEntityException
     */
    public function getImageFullUrl($product)
    {
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl("media");
        if ($product->getImage()) {
            return $mediaUrl . 'catalog/product' . $product->getImage();
        }
        $imageData = $this->helperImageFactory->create();
        return $this->assetRepos->getUrl($imageData->getPlaceholder('small_image'));
    }

    /**
     * Get Category Name
     *
     * @param mixed $productId
     * @throws NoSuchEntityException
     */
    public function getCategoryName($productId)
    {
        $storeId = $this->storeManager->getStore()->getId();
        $categoryIds = $this->productCategory->getCategoryIds($productId);
        $categoryName = '';
        if ($categoryIds) {
            $lastCategoryId = end($categoryIds);
            $categoryInstance = $this->categoryRepository->get($lastCategoryId, $storeId);
            $categoryName = $categoryInstance->getName();
        }
        return $categoryName;
    }

    /**
     * Check API header api-key and partner-id with configured auth key
     *
     * @param mixed $requestedApiKey
     * @param mixed $requestedPartnerId
     * @return bool
     */
    public function isValidateApiAuth($requestedApiKey, $requestedPartnerId)
    {
        $configuredApiKey = $this->getApiKey();
        $configuredPartnerId = $this->getPartnerId();
        if (strcmp($requestedApiKey, $configuredApiKey) ||
            strcmp($requestedPartnerId, $configuredPartnerId)
        ) {
            return false;
        } else {
            return true;
        }
    }
}
