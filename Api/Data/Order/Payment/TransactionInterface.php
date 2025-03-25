<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Order\Payment;

interface TransactionInterface
{
    public const TYPE = 'type';
    public const STATUS = 'status';
    public const PROVIDER_ID = 'provider_id';
    public const PROCESSED_AT = 'processed_at';
    public const AMOUNT = 'amount';

    /**
     * @param string $type
     * @return TransactionInterface
     */
    public function setType(string $type): TransactionInterface;

    /**
     * @return string
     */
    public function getType(): string;

    /**
     * @param string $status
     * @return TransactionInterface
     */
    public function setStatus(string $paymentMethod): TransactionInterface;

    /**
     * @return string
     */
    public function getStatus(): string;

    /**
     * @param string $providerId
     * @return TransactionInterface
     */
    public function setProviderId(string $providerId): TransactionInterface;

    /**
     * @return string
     */
    public function getProviderId(): string;

    /**
     * @param string $date
     * @return TransactionInterface
     */
    public function setProcessedAt(string $date): TransactionInterface;

    /**
     * @return string
     */
    public function getProcessedAt(): string;

    /**
     * @param float $amount
     * @return TransactionInterface
     */
    public function setAmount(float $amount): TransactionInterface;

    /**
     * @return float
     */
    public function getAmount(): float;
}
