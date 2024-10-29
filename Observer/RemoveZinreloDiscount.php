<?php

namespace Zinrelo\LoyaltyRewards\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Zinrelo\LoyaltyRewards\Helper\Data;

class RemoveZinreloDiscount implements ObserverInterface
{
    /**
     * @var CheckoutSession
     */
    private $checkoutSession;
    /**
     * @var Data
     */
    private $helper;

    /**
     * Remove Zinrelo Discount constructor.
     *
     * @param CheckoutSession $checkoutSession
     * @param Data $helper
     */
    public function __construct(
        CheckoutSession $checkoutSession,
        Data $helper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->helper = $helper;
    }

    /**
     * Remove Redeem Reward Discount
     *
     * @param Observer $observer
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $quote = $this->checkoutSession->getQuote();
        $zinreloQuote = $this->helper->getZinreloQuoteByQuoteId($quote->getId());
        if ($zinreloQuote->getIsAbandonedCartSent() || $zinreloQuote->getIsAbandonedCartSent() === null) {
            $this->helper->setAbandonedCartSent($quote->getId(), 2);
        }

        $cart = $observer->getEvent()->getCart();
        if ($cart->getItemsCount() > 1) {
            return true;
        } elseif ($cart->getItemsCount() === 1 && $zinreloQuote->getRedeemRewardDiscount()) {
            foreach ($quote->getAllItems() as $item) {
                $zinreloQuoteItem = $this->helper->getZinreloQuoteItemByItemId($item->getId());
                if ($zinreloQuoteItem->getIsZinreloFreeProduct()) {
                    $this->helper->sendRejectRewardRequest($quote);
                    $quote->delete();
                    return true;
                }
            }
        } elseif ($cart->getItemsCount() === 0 && $zinreloQuote->getRedeemRewardDiscount()) {
            $this->helper->sendRejectRewardRequest($quote);
            return true;
        }
    }
}
