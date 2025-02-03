<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\PaymentInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\TransactionInterface;
use Magento\Framework\DataObject;

class Payment extends DataObject implements PaymentInterface
{
    public function getCurrency(): string
    {
        return $this->getData(self::CURRENCY);
    }

    public function getCustomAttributes(): array
    {
        return $this->getData(self::CUSTOM_ATTRIBUTES);
    }

    public function getKey(): string
    {
        return $this->getData(self::KEY);
    }

    public function getPaymentMethod(): string
    {
        return $this->getData(self::PAYMENT_METHOD);
    }

    /**
     * @return mixed
     */
    public function getProvider()
    {
        return $this->getData(self::PROVIDER);
    }

    public function getTransaction(): TransactionInterface
    {
        return $this->getData(self::TRANSACTION);
    }

    public function setKey(string $key): PaymentInterface
    {
        $this->setData(self::KEY, $key);
        return $this;
    }

    public function setPaymentMethod(string $paymentMethod): PaymentInterface
    {
        $this->setData(self::PAYMENT_METHOD, $paymentMethod);
        return $this;
    }

    public function setProvider($provider): PaymentInterface
    {
        $this->setData(self::PROVIDER, $provider);
        return $this;
    }

    public function setCurrency(string $currency): PaymentInterface
    {
        $this->setData(self::CURRENCY, $currency);
        return $this;
    }

    public function setTransaction(TransactionInterface $transaction): PaymentInterface
    {
        $this->setData(self::TRANSACTION, $transaction);
        return $this;
    }

    public function setCustomAttributes(array $customAttributes): PaymentInterface
    {
        $this->setData(self::CUSTOM_ATTRIBUTES, $customAttributes);
        return $this;
    }
}
