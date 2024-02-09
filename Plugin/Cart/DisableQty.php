<?php

namespace Zinrelo\LoyaltyRewards\Plugin\Cart;

use Magento\Checkout\Block\Cart\AbstractCart;
use Magento\Checkout\Model\Session as CheckoutSession;

class DisableQty
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * Disable Qty for Free product
     *
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        CheckoutSession $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * After Get ItemRenderer
     *
     * @param AbstractCart $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterGetItemRenderer(AbstractCart $subject, $result)
    {
        $isFreeProduct = false;
        $quote = $this->checkoutSession->getQuote();
        $items = $quote->getAllItems();
        foreach ($items as $item) {
            if ($item->getIsZinreloFreeProduct()) {
                $isFreeProduct = true;
            }
        }
        if ($isFreeProduct) {
            $result->setTemplate('Zinrelo_LoyaltyRewards::cart/item/default.phtml');
        }
        return $result;
    }
}
