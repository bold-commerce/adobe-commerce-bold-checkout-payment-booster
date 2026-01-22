<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Integration\InventoryChecker;

use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;

/**
 * Factory for creating appropriate inventory checker based on available modules.
 */
class InventoryCheckerFactory
{
    /**
     * @var ModuleManager
     */
    private $moduleManager;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @param ModuleManager $moduleManager
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        ModuleManager $moduleManager,
        ObjectManagerInterface $objectManager
    ) {
        $this->moduleManager = $moduleManager;
        $this->objectManager = $objectManager;
    }

    /**
     * Create inventory checker based on available modules.
     *
     * @return InventoryCheckerInterface
     */
    public function create(): InventoryCheckerInterface
    {
        if ($this->isMsiEnabled()) {
            return $this->objectManager->get(MsiInventoryChecker::class);
        }

        return $this->objectManager->get(LegacyInventoryChecker::class);
    }

    /**
     * Check if MSI modules are enabled.
     *
     * @return bool
     */
    private function isMsiEnabled(): bool
    {
        return $this->moduleManager->isEnabled('Magento_Inventory')
            && $this->moduleManager->isEnabled('Magento_InventorySalesApi');
    }
}
