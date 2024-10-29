<?php
/**
 * Copyright © Zinrelo. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Zinrelo\LoyaltyRewards\Model\ResourceModel\ZinreloEavAttribute;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Zinrelo\LoyaltyRewards\Model\ZinreloEavAttribute as ZinreloEavAttributeModel;
use Zinrelo\LoyaltyRewards\Model\ResourceModel\ZinreloEavAttribute as ZinreloEavAttributeResourceModel;

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
            ZinreloEavAttributeModel::class,
            ZinreloEavAttributeResourceModel::class
        );
    }
}

