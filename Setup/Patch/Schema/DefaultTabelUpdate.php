<?php

namespace Zinrelo\LoyaltyRewards\Setup\Patch\Schema;

use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\SchemaPatchInterface;

class DefaultTabelUpdate implements SchemaPatchInterface
{
    /**
     * @var ModuleDataSetupInterface $moduleDataSetup
     */
    private $moduleDataSetup;

    /**
     * DefaultTabelUpdate construct
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * Add additional column to Existing tables
     *
     * @return void
     */
    public function apply()
    {
        $installer = $this->moduleDataSetup->startSetup();
        $installer->startSetup();
        $connection = $installer->getConnection();

        /*quote*/
        $quoteTable = $installer->getTable('quote');
        $quoteColumns = [
            'redeem_reward_discount' => [
                'type' => Table::TYPE_TEXT,
                'length' => "255",
                'nullable' => true,
                'comment' => 'Redeem Reward Discount',
            ],
            'reward_rules_data' => [
                'type' => Table::TYPE_TEXT,
                'nullable' => false,
                'comment' => 'Reward Rules Data',
            ],
            'is_abandoned_cart_sent' => [
                'type' => Table::TYPE_SMALLINT,
                ['unsigned' => true, 'nullable' => true, 'identity' => false],
                'comment' => 'Is abandoned cart request sent',
            ]
        ];
        foreach ($quoteColumns as $name => $definition) {
            $connection->addColumn($quoteTable, $name, $definition);
        }

        /*quote_item*/
        $quoteItemTable = $installer->getTable('quote_item');
        $quoteItemColumn = [
            'type' => Table::TYPE_SMALLINT,
            ['unsigned' => true, 'nullable' => false, 'identity' => false, 'default' => "0"],
            'comment' => 'Is Zinrelo Free Product',
        ];
        $connection->addColumn($quoteItemTable, 'is_zinrelo_free_product', $quoteItemColumn);

        /*customer_eav_attribute*/
        $customerEavAttributeTable = $installer->getTable('customer_eav_attribute');
        $customerEavAttributeColumn = [
            'type' => Table::TYPE_SMALLINT,
            ['unsigned' => true, 'nullable' => false, 'identity' => false, 'default' => "0"],
            'comment' => 'Is Zinrelo Attribute',
        ];
        $connection->addColumn($customerEavAttributeTable, 'is_zinrelo_attribute', $customerEavAttributeColumn);

        /*eav_attribute*/
        $eavAttributeTable = $installer->getTable('eav_attribute');
        $eavAttributeColumn = [
            'type' => Table::TYPE_SMALLINT,
            ['unsigned' => true, 'nullable' => false, 'identity' => false, 'default' => "0"],
            'comment' => 'Is Zinrelo Attribute',
        ];
        $connection->addColumn($eavAttributeTable, 'is_zinrelo_attribute', $eavAttributeColumn);

        /*sales_order*/
        $salesOrderTable = $installer->getTable('sales_order');
        $salesOrderColumns = [
            'zinrelo_reward' => [
                'type' => Table::TYPE_TEXT,
                'nullable' => true,
                'comment' => 'Add applied reward rule data',
            ],
            'complete_request_sent' => [
                'type' => Table::TYPE_SMALLINT,
                ['unsigned' => true, 'nullable' => false, 'identity' => false, 'default' => "0"],
                'comment' => 'Is order complete request sent to Zinrelo',
            ]
        ];
        foreach ($salesOrderColumns as $name => $definition) {
            $connection->addColumn($salesOrderTable, $name, $definition);
        }

        /*review*/
        $reviewTable = $installer->getTable('review');
        $reviewColumn = [
            'type' => Table::TYPE_SMALLINT,
            ['unsigned' => true, 'nullable' => false, 'identity' => false, 'default' => "0"],
            'comment' => 'Is product review submitted Zinrelo',
        ];
        $connection->addColumn($reviewTable, 'submitted_to_zinrelo', $reviewColumn);

        $installer->endSetup();
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
