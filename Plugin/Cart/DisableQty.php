<?php

namespace Zinrelo\LoyaltyRewards\Plugin\Cart;

use Magento\Checkout\Block\Cart\AbstractCart;
use Magento\Checkout\Model\Session as CheckoutSession;
use Zinrelo\LoyaltyRewards\Helper\Config as ZinreloHelper;

class DisableQty
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;
    /**
     * @var ZinreloHelper
     */
    private $zinreloHelper;

    /**
     * Disable Qty for Free product
     *
     * @param CheckoutSession $checkoutSession
     * @param ZinreloHelper $zinreloHelper
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        ZinreloHelper $zinreloHelper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->zinreloHelper = $zinreloHelper;
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
            $zinreloQuoteItem = $this->zinreloHelper->getZinreloQuoteItemByItemId($item->getId());
            if ($zinreloQuoteItem->getIsZinreloFreeProduct()) {
                $isFreeProduct = true;
            }
        }
        if ($isFreeProduct) {
            $result->setTemplate('Zinrelo_LoyaltyRewards::cart/item/default.phtml');
        }
        return $result;
    }
}
