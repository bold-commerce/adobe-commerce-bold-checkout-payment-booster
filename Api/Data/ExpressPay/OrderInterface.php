<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\ExpressPay;

use Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\Order\AddressInterface;

/**
 * @method OrderInterface setData(string|null $data)
 * @method string|null getData()
 */
interface OrderInterface
{
    public const FIRST_NAME = 'first_name';
    public const LAST_NAME = 'last_name';
    public const EMAIL = 'email';
    public const SHIPPING_ADDRESS = 'shipping_address';
    public const BILLING_ADDRESS = 'billing_address';

    /**
     * @param string $firstName
     * @return \Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\OrderInterface
     */
    public function setFirstName(string $firstName): OrderInterface;

    /**
     * @return string
     */
    public function getFirstName(): string;

    /**
     * @param string $lastName
     * @return \Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\OrderInterface
     */
    public function setLastName(string $lastName): OrderInterface;

    /**
     * @return string
     */
    public function getLastName(): string;

    /**
     * @param string $email
     * @return \Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\OrderInterface
     */
    public function setEmail(string $email): OrderInterface;

    /**
     * @return string
     */
    public function getEmail(): string;

    /**
     * @param \Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\Order\AddressInterface $address
     * @return \Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\OrderInterface
     */
    public function setShippingAddress(AddressInterface $address): OrderInterface;

    /**
     * @return \Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\Order\AddressInterface
     */
    public function getShippingAddress(): AddressInterface;

    /**
     * @param \Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\Order\AddressInterface $address
     * @return \Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\OrderInterface
     */
    public function setBillingAddress(AddressInterface $address): OrderInterface;

    /**
     * @return \Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\Order\AddressInterface
     */
    public function getBillingAddress(): AddressInterface;
}
