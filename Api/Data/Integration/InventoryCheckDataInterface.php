<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Integration;

/**
 * Inventory check data interface.
 */
interface InventoryCheckDataInterface
{
    /**
     * Get inventory check results.
     *
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterface[]
     */
    public function getResults(): array;

    /**
     * Set inventory check results.
     *
     * @param \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterface[] $results
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryCheckDataInterface
     */
    public function setResults(array $results): \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryCheckDataInterface;

    /**
     * Get overall availability (true if all items available).
     *
     * @return bool
     */
    public function getIsAvailable(): bool;

    /**
     * Set overall availability.
     *
     * @param bool $isAvailable
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryCheckDataInterface
     */
    public function setIsAvailable(bool $isAvailable): \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryCheckDataInterface;
}
