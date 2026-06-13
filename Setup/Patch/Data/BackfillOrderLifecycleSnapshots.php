<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Backfill order-level lifecycle snapshots from quote relations for existing orders.
 */
class BackfillOrderLifecycleSnapshots implements DataPatchInterface
{
    /** @var ModuleDataSetupInterface */
    private $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritDoc
     */
    public function apply(): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $orderTable = $this->moduleDataSetup->getTable('bold_checkout_payment_booster_order');
        $salesOrderTable = $this->moduleDataSetup->getTable('sales_order');
        $quoteRelationTable = $this->moduleDataSetup->getTable('bold_booster_magento_quote_bold_order');

        $connection->query(
            "UPDATE {$orderTable} AS bo
            INNER JOIN {$salesOrderTable} AS so ON so.entity_id = bo.order_id
            INNER JOIN {$quoteRelationTable} AS qbo ON qbo.quote_id = so.quote_id
            SET
                bo.successful_hydrate_at = COALESCE(bo.successful_hydrate_at, qbo.successful_hydrate_at),
                bo.successful_auth_full_at = COALESCE(bo.successful_auth_full_at, qbo.successful_auth_full_at),
                bo.successful_state_at = COALESCE(bo.successful_state_at, qbo.successful_state_at)
            WHERE qbo.successful_hydrate_at IS NOT NULL
               OR qbo.successful_auth_full_at IS NOT NULL
               OR qbo.successful_state_at IS NOT NULL"
        );
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
