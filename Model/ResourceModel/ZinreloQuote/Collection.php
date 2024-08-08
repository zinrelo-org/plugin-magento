<?php
/**
 * Copyright © Zinrelo. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Zinrelo\LoyaltyRewards\Model\ResourceModel\ZinreloQuote;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Zinrelo\LoyaltyRewards\Model\ZinreloQuote as ZinreloQuoteModel;
use Zinrelo\LoyaltyRewards\Model\ResourceModel\ZinreloQuote as ZinreloQuoteResourceModel;

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
            ZinreloQuoteModel::class,
            ZinreloQuoteResourceModel::class
        );
    }
}

