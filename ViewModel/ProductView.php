<?php
namespace Zinrelo\LoyaltyRewards\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Zinrelo\LoyaltyRewards\Helper\Data;

class ProductView implements ArgumentInterface
{
    /**
     * @var Data
     */
    public $helper;

    /**
     * ProductView ViewModel constructor.
     *
     * @param Data $helper
     */
    public function __construct(
        Data $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * Get reward label
     *
     * @return string
     */
    public function getRewardLabel()
    {
        return $this->helper->getRewardLabelAtProductPage();
    }

    /**
     * Check Reward Point can show at PDP or not
     *
     * @return bool
     */
    public function isPdpPointEnabled()
    {
        return $this->helper->isRewardPointAtPdpEnabled();
    }
}
