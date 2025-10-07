<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Diagnostic;

/**
 * Website configuration interface
 */
interface WebsiteConfigurationInterface
{
    public const COUNTRY = 'country';
    public const CURRENCY = 'currency';
    public const LOCALE = 'locale';
    public const TIMEZONE = 'timezone';
    public const IS_SINGLE_STORE = 'is_single_store';
    public const BASE_URL = 'base_url';
    /**
     * Get country
     *
     * @return string
     */
    public function getCountry(): string;

    /**
     * Set country
     *
     * @param string $country
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\WebsiteConfigurationInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setCountry(string $country): WebsiteConfigurationInterface;

    /**
     * Get currency
     *
     * @return string
     */
    public function getCurrency(): string;

    /**
     * Set currency
     *
     * @param string $currency
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\WebsiteConfigurationInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setCurrency(string $currency): WebsiteConfigurationInterface;

    /**
     * Get locale
     *
     * @return string
     */
    public function getLocale(): string;

    /**
     * Set locale
     *
     * @param string $locale
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\WebsiteConfigurationInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setLocale(string $locale): WebsiteConfigurationInterface;

    /**
     * Get timezone
     *
     * @return string
     */
    public function getTimezone(): string;

    /**
     * Set timezone
     *
     * @param string $timezone
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\WebsiteConfigurationInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setTimezone(string $timezone): WebsiteConfigurationInterface;

    /**
     * Get is single store
     *
     * @return bool
     */
    public function getIsSingleStore(): bool;

    /**
     * Set is single store
     *
     * @param bool $isSingleStore
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\WebsiteConfigurationInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setIsSingleStore(bool $isSingleStore): WebsiteConfigurationInterface;

    /**
     * Get base URL
     *
     * @return string
     */
    public function getBaseUrl(): string;

    /**
     * Set base URL
     *
     * @param string $baseUrl
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\WebsiteConfigurationInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setBaseUrl(string $baseUrl): WebsiteConfigurationInterface;
}
