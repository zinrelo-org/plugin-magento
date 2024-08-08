<?php
/**
 * Copyright © Zinrelo. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Zinrelo\LoyaltyRewards\Model\ResourceModel\ZinreloQuoteItem;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Zinrelo\LoyaltyRewards\Model\ZinreloQuoteItem as ZinreloQuoteItemModel;
use Zinrelo\LoyaltyRewards\Model\ResourceModel\ZinreloQuoteItem as ZinreloQuoteItemResourceModel;

class Collection extends AbstractCollection
{

    /**
     * @inheritDoc
     */
    protected $_idFieldName = 'id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(
            ZinreloQuoteItemModel::class,
            ZinreloQuoteItemResourceModel::class
        );
    }
}

