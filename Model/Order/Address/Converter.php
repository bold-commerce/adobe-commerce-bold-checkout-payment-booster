<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\Address;

use Magento\Directory\Model\CountryFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;

/**
 * Quote address to bold address converter.
 */
class Converter
{
    /**
     * @var CountryFactory
     */
    private $countryFactory;

    /**
     * @var RegionFactory
     */
    private $regionFactory;

    /**
     * @param CountryFactory $countryFactory
     * @param ResolverInterface $resolver
     */
    public function __construct(CountryFactory $countryFactory, RegionFactory $regionFactory)
    {
        $this->countryFactory = $countryFactory;
        $this->regionFactory = $regionFactory;
    }

    /**
     * Convert order address to array.
     *
     * @param OrderAddressInterface $address
     * @return array
     */
    public function convert(OrderAddressInterface $address): array
    {
        $region = $this->regionFactory->create()->load($address->getRegionId());
        $country = $this->countryFactory->create()->loadByCode($address->getCountryId());
        return [
            'id' => (int)$address->getId() ?: null,
            'business_name' => (string)$address->getCompany(),
            'country_code' => (string)$address->getCountryId(),
            'country' => (string)$country->getName('en_US'),
            'city' => (string)$address->getCity(),
            'first_name' => (string)$address->getFirstname(),
            'last_name' => (string)$address->getLastname(),
            'phone_number' => (string)$address->getTelephone(),
            'postal_code' => (string)$address->getPostcode(),
            'province' => (string)$region->getDefaultName(),
            'province_code' => (string)$address->getRegionCode(),
            'address_line_1' => (string)($address->getStreet()[0] ?? ''),
            'address_line_2' => (string)($address->getStreet()[1] ?? ''),
        ];
    }
}
