<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Order\Payment;

interface PaymentInterface
{
    public const KEY = 'key';
    public const PAYMENT_METHOD = 'payment_method';
    public const PROVIDER = 'provider';
    public const CURRENCY = 'currency';
    public const TRANSACTION = 'transaction';
    public const CUSTOM_ATTRIBUTES = 'custom_attributes';

    /**
     * @param string $key
     * @return PaymentInterface
     */
    public function setKey(string $key): PaymentInterface;

    /**
     * @return string
     */
    public function getKey(): string;

    /**
     * @param string $paymentMethod
     * @return PaymentInterface
     */
    public function setPaymentMethod(string $paymentMethod): PaymentInterface;

    /**
     * @return string
     */
    public function getPaymentMethod(): string;

    /**
     * @param $provider
     * @return PaymentInterface
     */
    public function setProvider($provider): PaymentInterface;

    /**
     * @return mixed
     */
    public function getProvider();

    /**
     * @param string $currency
     * @return PaymentInterface
     */
    public function setCurrency(string $currency): PaymentInterface;

    /**
     * @return string
     */
    public function getCurrency(): string;

    /**
     * @param \Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\TransactionInterface $transaction
     * @return PaymentInterface
     */
    public function setTransaction(TransactionInterface $transaction): PaymentInterface;

    /**
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\TransactionInterface
     */
    public function getTransaction(): TransactionInterface;

    /**
     * @param array $customAttributes
     * @return PaymentInterface
     */
    public function setCustomAttributes(array $customAttributes): PaymentInterface;

    /**
     * @return array
     */
    public function getCustomAttributes(): array;
}
