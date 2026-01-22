<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Data\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryCheckDataInterface;

/**
 * Inventory check data model.
 */
class InventoryCheckData implements InventoryCheckDataInterface
{
    /**
     * @var \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryItemResultInterface[]
     */
    private $results = [];

    /**
     * @var bool
     */
    private $isAvailable = true;

    /**
     * @inheritDoc
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @inheritDoc
     */
    public function setResults(array $results): InventoryCheckDataInterface
    {
        $this->results = $results;
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
    public function setIsAvailable(bool $isAvailable): InventoryCheckDataInterface
    {
        $this->isAvailable = $isAvailable;
        return $this;
    }
}
