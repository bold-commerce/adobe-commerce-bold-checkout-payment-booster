<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Integration\InventoryChecker;

/**
 * Inventory checker interface.
 */
interface InventoryCheckerInterface
{
    /**
     * Check inventory for products.
     *
     * @param array<int, array{product: \Magento\Catalog\Api\Data\ProductInterface, quantity: float}> $productItems
     * @param mixed $context Website for MSI, websiteId for legacy
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterface[]
     */
    public function checkInventory(array $productItems, $context): array;
}
