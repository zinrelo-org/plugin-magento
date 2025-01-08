<?php
/**
 * Copyright © Zinrelo. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Zinrelo\LoyaltyRewards\Model\ResourceModel\ZinreloReview;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Zinrelo\LoyaltyRewards\Model\ZinreloReview as ZinreloReviewModel;
use Zinrelo\LoyaltyRewards\Model\ResourceModel\ZinreloReview as ZinreloReviewResourceModel;

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
            ZinreloReviewModel::class,
            ZinreloReviewResourceModel::class
        );
    }
}

