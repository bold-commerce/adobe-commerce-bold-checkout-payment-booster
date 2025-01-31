<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\TransactionInterface;
use Magento\Framework\DataObject;

class Transaction extends DataObject implements TransactionInterface
{
    public function getAmount(): float
    {
        return $this->getData(self::AMOUNT);
    }

    public function setAmount(float $amount): TransactionInterface
    {
        $this->setData(self::AMOUNT, $amount);
        return $this;
    }

    public function getProcessedAt(): string
    {
        return $this->getData(self::PROCESSED_AT);
    }

    public function setProcessedAt(string $processedAt): TransactionInterface
    {
        $this->setData(self::PROCESSED_AT, $processedAt);
        return $this;
    }

    public function getProviderId(): string
    {
        return $this->getData(self::PROVIDER_ID);
    }

    public function setProviderId(string $providerId): TransactionInterface
    {
        $this->setData(self::PROVIDER_ID, $providerId);
        return $this;
    }

    public function getStatus(): string
    {
        return $this->getData(self::STATUS);
    }

    public function setStatus(string $status): TransactionInterface
    {
        $this->setData(self::STATUS, $status);
        return $this;
    }

    public function getType(): string
    {
        return $this->getData(self::TYPE);
    }

    public function setType(string $type): TransactionInterface
    {
        $this->setData(self::TYPE, $type);
        return $this;
    }
}
