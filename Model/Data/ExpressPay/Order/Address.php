<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Data\ExpressPay\Order;

use Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\Order\AddressInterface;
use Magento\Framework\DataObject;

class Address extends DataObject implements AddressInterface
{
    /**
     * @inheritDoc
     */
    public function setAddressLine1(string $addressLine1): AddressInterface
    {
        $this->setData(self::ADDRESS_LINE_1, $addressLine1);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getAddressLine1(): string
    {
        return $this->getData(self::ADDRESS_LINE_1);
    }

    /**
     * @inheritDoc
     */
    public function setAddressLine2(string $addressLine2): AddressInterface
    {
        $this->setData(self::ADDRESS_LINE_2, $addressLine2);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getAddressLine2(): string
    {
        return $this->getData(self::ADDRESS_LINE_2);
    }

    /**
     * @inheritDoc
     */
    public function setCity(string $city): AddressInterface
    {
        $this->setData(self::CITY, $city);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getCity(): string
    {
        return $this->getData(self::CITY);
    }

    /**
     * @inheritDoc
     */
    public function setCountry(string $country): AddressInterface
    {
        $this->setData(self::COUNTRY, $country);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getCountry(): string
    {
        return $this->getData(self::COUNTRY);
    }

    /**
     * @inheritDoc
     */
    public function setProvince(string $province): AddressInterface
    {
        $this->setData(self::PROVINCE, $province);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getProvince(): string
    {
        return $this->getData(self::PROVINCE);
    }

    /**
     * @inheritDoc
     */
    public function setPostalCode(string $postalCode): AddressInterface
    {
        $this->setData(self::POSTAL_CODE, $postalCode);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getPostalCode(): string
    {
        return $this->getData(self::POSTAL_CODE);
    }

    /**
     * @inheritDoc
     */
    public function setPhone(string $phone): AddressInterface
    {
        $this->setData(self::PHONE, $phone);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getPhone(): ?string
    {
        return $this->getData(self::PHONE);
    }
}
