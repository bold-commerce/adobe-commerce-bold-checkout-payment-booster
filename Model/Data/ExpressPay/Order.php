<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Data\ExpressPay;

use Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\Order\AddressInterface;
use Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\OrderInterface;
use Magento\Framework\DataObject;

class Order extends DataObject implements OrderInterface
{
    /**
     * @inheritDoc
     */
    public function setFirstName(string $firstName): OrderInterface
    {
        $this->setData(self::FIRST_NAME, $firstName);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getFirstName(): string
    {
        return $this->getData(self::FIRST_NAME);
    }

    /**
     * @inheritDoc
     */
    public function setLastName(string $lastName): OrderInterface
    {
        $this->setData(self::LAST_NAME, $lastName);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getLastName(): string
    {
        return $this->getData(self::LAST_NAME);
    }

    /**
     * @inheritDoc
     */
    public function setEmail(string $email): OrderInterface
    {
        $this->setData(self::EMAIL, $email);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getEmail(): string
    {
        return $this->getData(self::EMAIL);
    }

    /**
     * @inheritDoc
     */
    public function setShippingAddress(AddressInterface $address): OrderInterface
    {
        $this->setData(self::SHIPPING_ADDRESS, $address);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getShippingAddress(): AddressInterface
    {
        return $this->getData(self::SHIPPING_ADDRESS);
    }

    /**
     * @inheritDoc
     */
    public function setBillingAddress(AddressInterface $address): OrderInterface
    {
        $this->setData(self::BILLING_ADDRESS, $address);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getBillingAddress(): AddressInterface
    {
        return $this->getData(self::BILLING_ADDRESS);
    }
}
