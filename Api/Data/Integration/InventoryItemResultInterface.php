<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Integration;

/**
 * Inventory check result for a single item.
 */
interface InventoryItemResultInterface
{
    /**
     * Get product SKU.
     *
     * @return string
     */
    public function getSku(): string;

    /**
     * Set product SKU.
     *
     * @param string $sku
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterface
     */
    public function setSku(string $sku): \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterface;

    /**
     * Get product ID.
     *
     * @return string
     */
    public function getProductId(): string;

    /**
     * Set product ID.
     *
     * @param string $productId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterface
     */
    public function setProductId(string $productId): \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterface;

    /**
     * Get requested quantity.
     *
     * @return float
     */
    public function getRequestedQuantity(): float;

    /**
     * Set requested quantity.
     *
     * @param float $requestedQuantity
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterface
     */
    public function setRequestedQuantity(float $requestedQuantity): \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterface;

    /**
     * Get available quantity.
     *
     * @return float
     */
    public function getAvailableQuantity(): float;

    /**
     * Set available quantity.
     *
     * @param float $availableQuantity
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterface
     */
    public function setAvailableQuantity(float $availableQuantity): \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterface;

    /**
     * Get availability status.
     *
     * @return bool
     */
    public function getIsAvailable(): bool;

    /**
     * Set availability status.
     *
     * @param bool $isAvailable
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterface
     */
    public function setIsAvailable(bool $isAvailable): \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterface;

    /**
     * Get error message (if not available).
     *
     * @return string|null
     */
    public function getReason(): ?string;

    /**
     * Set error message.
     *
     * @param string|null $reason
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterface
     */
    public function setReason(?string $reason): \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterface;
}
