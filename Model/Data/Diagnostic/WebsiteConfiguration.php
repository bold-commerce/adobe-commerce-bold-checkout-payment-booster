<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Data\Diagnostic;

use Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\WebsiteConfigurationInterface;
use Magento\Framework\DataObject;

/**
 * Website configuration data model
 */
class WebsiteConfiguration extends DataObject implements WebsiteConfigurationInterface
{
    /**
     * Get country
     *
     * @return string
     */
    public function getCountry(): string
    {
        return (string) $this->getData(self::COUNTRY);
    }

    /**
     * Set country
     *
     * @param string $country
     * @return WebsiteConfigurationInterface
     */
    public function setCountry(string $country): WebsiteConfigurationInterface
    {
        return $this->setData(self::COUNTRY, $country);
    }

    /**
     * Get currency
     *
     * @return string
     */
    public function getCurrency(): string
    {
        return (string) $this->getData(self::CURRENCY);
    }

    /**
     * Set currency
     *
     * @param string $currency
     * @return WebsiteConfigurationInterface
     */
    public function setCurrency(string $currency): WebsiteConfigurationInterface
    {
        return $this->setData(self::CURRENCY, $currency);
    }

    /**
     * Get locale
     *
     * @return string
     */
    public function getLocale(): string
    {
        return (string) $this->getData(self::LOCALE);
    }

    /**
     * Set locale
     *
     * @param string $locale
     * @return WebsiteConfigurationInterface
     */
    public function setLocale(string $locale): WebsiteConfigurationInterface
    {
        return $this->setData(self::LOCALE, $locale);
    }

    /**
     * Get timezone
     *
     * @return string
     */
    public function getTimezone(): string
    {
        return (string) $this->getData(self::TIMEZONE);
    }

    /**
     * Set timezone
     *
     * @param string $timezone
     * @return WebsiteConfigurationInterface
     */
    public function setTimezone(string $timezone): WebsiteConfigurationInterface
    {
        return $this->setData(self::TIMEZONE, $timezone);
    }

    /**
     * Get is single store
     *
     * @return bool
     */
    public function getIsSingleStore(): bool
    {
        return (bool) $this->getData(self::IS_SINGLE_STORE);
    }

    /**
     * Set is single store
     *
     * @param bool $isSingleStore
     * @return WebsiteConfigurationInterface
     */
    public function setIsSingleStore(bool $isSingleStore): WebsiteConfigurationInterface
    {
        return $this->setData(self::IS_SINGLE_STORE, $isSingleStore);
    }

    /**
     * Get base URL
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return (string) $this->getData(self::BASE_URL);
    }

    /**
     * Set base URL
     *
     * @param string $baseUrl
     * @return WebsiteConfigurationInterface
     */
    public function setBaseUrl(string $baseUrl): WebsiteConfigurationInterface
    {
        return $this->setData(self::BASE_URL, $baseUrl);
    }
}
