<?php
/**
 * Copyright © Zinrelo. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Zinrelo\LoyaltyRewards\Model\ResourceModel\ZinreloSalesOrder;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Zinrelo\LoyaltyRewards\Model\ZinreloSalesOrder as ZinreloSalesOrderModel;
use Zinrelo\LoyaltyRewards\Model\ResourceModel\ZinreloSalesOrder as ZinreloSalesOrderResourceModel;
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
            ZinreloSalesOrderModel::class,
            ZinreloSalesOrderResourceModel::class
        );
    }
}

