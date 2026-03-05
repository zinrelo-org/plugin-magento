<?php

namespace TrueLoyal\LoyaltyRewards\Model\System\Config;

use Magento\Framework\Data\OptionSourceInterface;

class PreferredLanguage implements OptionSourceInterface
{
    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '',                      'label' => __('-- Please Select --')],
            ['value' => 'english',               'label' => __('English')],
            ['value' => 'arabic',                'label' => __('Arabic')],
            ['value' => 'french',                'label' => __('French')],
            ['value' => 'german',                'label' => __('German')],
            ['value' => 'italian',               'label' => __('Italian')],
            ['value' => 'spanish',               'label' => __('Spanish')],
            ['value' => 'custom language one',   'label' => __('Custom Language One')],
            ['value' => 'custom language two',   'label' => __('Custom Language Two')],
            ['value' => 'custom language three', 'label' => __('Custom Language Three')],
            ['value' => 'custom language four',  'label' => __('Custom Language Four')],
        ];
    }
}
