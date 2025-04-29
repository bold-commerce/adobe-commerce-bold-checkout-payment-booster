<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Setup;

use Bold\CheckoutPaymentBooster\Model\Migration;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\HTTP\Client\Curl;

class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var Curl
     */
    private $curl;

    private $migration;

    /**
     * Constructor
     *
     * @param Curl $curl
     * @param Migration $migration
     */
    public function __construct(
        Curl $curl,
        Migration $migration
    ){
        $this->curl = $curl;
        $this->migration = $migration;
    }

    /**
     * Upgrade data
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     * @throws LocalizedException
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        // Check if current installed version is less than 3.0.0
        if (version_compare($context->getVersion(), '3.0.0', '<')) {
            try {
                $this->migration->migrateConfig();
            } catch (LocalizedException $e) {
                //Localized exception
            }
        }
        $setup->endSetup();
    }
}
