<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\Order;

/**
 * @method AddressInterface setData(string|null $data)
 * @method string|null getData()
 */
interface AddressInterface
{
    public const ADDRESS_LINE_1 = 'address_line_1';
    public const ADDRESS_LINE_2 = 'address_line_2';
    public const CITY = 'city';
    public const COUNTRY = 'country';
    public const PROVINCE = 'province';
    public const POSTAL_CODE = 'postal_code';
    public const PHONE = 'phone';

    /**
     * @param string $addressLine1
     * @return \Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\Order\AddressInterface
     */
    public function setAddressLine1(string $addressLine1): AddressInterface;

    /**
     * @return string
     */
    public function getAddressLine1(): string;

    /**
     * @param string $addressLine2
     * @return \Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\Order\AddressInterface
     */
    public function setAddressLine2(string $addressLine2): AddressInterface;

    /**
     * @return string
     */
    public function getAddressLine2(): string;

    /**
     * @param string $city
     * @return \Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\Order\AddressInterface
     */
    public function setCity(string $city): AddressInterface;

    /**
     * @return string
     */
    public function getCity(): string;

    /**
     * @param string $country
     * @return \Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\Order\AddressInterface
     */
    public function setCountry(string $country): AddressInterface;

    /**
     * @return string
     */
    public function getCountry(): string;

    /**
     * @param string $province
     * @return \Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\Order\AddressInterface
     */
    public function setProvince(string $province): AddressInterface;

    /**
     * @return string
     */
    public function getProvince(): string;

    /**
     * @param string $postalCode
     * @return \Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\Order\AddressInterface
     */
    public function setPostalCode(string $postalCode): AddressInterface;

    /**
     * @return string
     */
    public function getPostalCode(): string;

    /**
     * @param string $phone
     * @return \Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\Order\AddressInterface
     */
    public function setPhone(string $phone): AddressInterface;

    /**
     * @return string|null
     */
    public function getPhone(): ?string;
}
