<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Integration;

/**
 * Inventory Check Integration API.
 */
interface InventoryCheckApiInterface
{
    /**
     * Check inventory availability for products.
     *
     * @param string $shopId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryCheckResponseInterface
     */
    public function check(string $shopId): \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryCheckResponseInterface;
}
