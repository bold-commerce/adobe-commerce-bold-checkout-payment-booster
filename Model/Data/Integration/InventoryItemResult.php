<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Data\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterface;

/**
 * Inventory check result for a single item.
 */
class InventoryItemResult implements InventoryItemResultInterface
{
    /**
     * @var string
     */
    private $sku;

    /**
     * @var string
     */
    private $productId;

    /**
     * @var float
     */
    private $requestedQuantity;

    /**
     * @var float
     */
    private $availableQuantity;

    /**
     * @var bool
     */
    private $isAvailable;

    /**
     * @var string|null
     */
    private $reason;

    /**
     * @inheritDoc
     */
    public function getSku(): string
    {
        return $this->sku;
    }

    /**
     * @inheritDoc
     */
    public function setSku(string $sku): InventoryItemResultInterface
    {
        $this->sku = $sku;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getProductId(): string
    {
        return $this->productId;
    }

    /**
     * @inheritDoc
     */
    public function setProductId(string $productId): InventoryItemResultInterface
    {
        $this->productId = $productId;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getRequestedQuantity(): float
    {
        return $this->requestedQuantity;
    }

    /**
     * @inheritDoc
     */
    public function setRequestedQuantity(float $requestedQuantity): InventoryItemResultInterface
    {
        $this->requestedQuantity = $requestedQuantity;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getAvailableQuantity(): float
    {
        return $this->availableQuantity;
    }

    /**
     * @inheritDoc
     */
    public function setAvailableQuantity(float $availableQuantity): InventoryItemResultInterface
    {
        $this->availableQuantity = $availableQuantity;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getIsAvailable(): bool
    {
        return $this->isAvailable;
    }

    /**
     * @inheritDoc
     */
    public function setIsAvailable(bool $isAvailable): InventoryItemResultInterface
    {
        $this->isAvailable = $isAvailable;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * @inheritDoc
     */
    public function setReason(?string $reason): InventoryItemResultInterface
    {
        $this->reason = $reason;
        return $this;
    }
}
