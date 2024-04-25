<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Migrate checkout type configuration.
 */
class MigrateCheckoutTypeConfigurationPatch implements DataPatchInterface
{
    private const SOURCE_TYPE = 2;
    private const PATH_SOURCE = 'checkout/bold_checkout_base/type';
    private const PATH_TARGET = 'checkout/bold_checkout_payment_booster/is_enabled';

    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * Perform integration permissions upgrade.
     *
     * @return void
     */
    public function apply(): void
    {
        $this->moduleDataSetup->startSetup();

        $connection = $this->moduleDataSetup->getConnection();
        $table = $connection->getTableName('core_config_data');
        $select = $connection->select()->from(
            $table,
            [
                'scope',
                'scope_id',
                'value',
            ]
        )->where(
            'path = ?',
            self::PATH_SOURCE
        );
        $sources = $connection->fetchAll($select);
        foreach ($sources as $source) {
            $data = [
                'scope' => $source['scope'],
                'scope_id' => $source['scope_id'],
                'path' => self::PATH_TARGET,
                'value' => (int)$source['value'] === self::SOURCE_TYPE ? 1 : 0,
            ];
            $connection->insertOnDuplicate($table, $data, ['value']);
        }

        $this->moduleDataSetup->endSetup();
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }
}
