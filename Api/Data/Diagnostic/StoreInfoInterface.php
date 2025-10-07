<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Diagnostic;

/**
 * Store information interface
 */
interface StoreInfoInterface
{
    public const WEBSITE_ID = 'website_id';
    public const STORE_ID = 'store_id';
    public const STORE_NAME = 'store_name';
    public const WEBSITE_CONFIGURATION = 'website_configuration';

    /**
     * Get website ID
     *
     * @return int
     */
    public function getWebsiteId(): int;

    /**
     * Set website ID
     *
     * @param int $websiteId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\StoreInfoInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setWebsiteId(int $websiteId): StoreInfoInterface;

    /**
     * Get store ID
     *
     * @return string
     */
    public function getStoreId(): string;

    /**
     * Set store ID
     *
     * @param string $storeId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\StoreInfoInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setStoreId(string $storeId): StoreInfoInterface;

    /**
     * Get store name
     *
     * @return string
     */
    public function getStoreName(): string;

    /**
     * Set store name
     *
     * @param string $storeName
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\StoreInfoInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setStoreName(string $storeName): StoreInfoInterface;

    /**
     * Get website configuration
     *
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\WebsiteConfigurationInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function getWebsiteConfiguration(): WebsiteConfigurationInterface;

    /**
     * Set website configuration
     *
     * @param \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\WebsiteConfigurationInterface $websiteConfiguration
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\StoreInfoInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setWebsiteConfiguration(WebsiteConfigurationInterface $websiteConfiguration): StoreInfoInterface;
}
