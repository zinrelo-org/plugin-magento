<?php
/**
 * Copyright ©  Zinrelo. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Zinrelo\LoyaltyRewards\Model;

use Magento\Framework\Model\AbstractModel;
use Zinrelo\LoyaltyRewards\Model\ResourceModel\ZinreloEavAttribute as ZinreloEavAttributeResourceModel;

class ZinreloEavAttribute extends AbstractModel
{

    /**
     * @inheritDoc
     */
    public function _construct()
    {
        $this->_init(ZinreloEavAttributeResourceModel::class);
    }
}

