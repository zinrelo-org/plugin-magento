<?php
/**
 * Copyright © Zinrelo. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Zinrelo\LoyaltyRewards\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class ZinreloQuote extends AbstractDb
{

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('zinrelo_quote', 'id');
    }
}

