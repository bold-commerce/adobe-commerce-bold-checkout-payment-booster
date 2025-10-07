<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Data\Diagnostic;

use Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\StoreInfoInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\WebsiteConfigurationInterface;
use Magento\Framework\DataObject;

/**
 * Store information data model
 */
class StoreInfo extends DataObject implements StoreInfoInterface
{
    /**
     * Get website ID
     *
     * @return int
     */
    public function getWebsiteId(): int
    {
        return (int) $this->getData(self::WEBSITE_ID);
    }

    /**
     * Set website ID
     *
     * @param int $websiteId
     * @return StoreInfoInterface
     */
    public function setWebsiteId(int $websiteId): StoreInfoInterface
    {
        return $this->setData(self::WEBSITE_ID, $websiteId);
    }

    /**
     * Get store ID
     *
     * @return string
     */
    public function getStoreId(): string
    {
        return (string) $this->getData(self::STORE_ID);
    }

    /**
     * Set store ID
     *
     * @param string $storeId
     * @return StoreInfoInterface
     */
    public function setStoreId(string $storeId): StoreInfoInterface
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    /**
     * Get store name
     *
     * @return string
     */
    public function getStoreName(): string
    {
        return (string) $this->getData(self::STORE_NAME);
    }

    /**
     * Set store name
     *
     * @param string $storeName
     * @return StoreInfoInterface
     */
    public function setStoreName(string $storeName): StoreInfoInterface
    {
        return $this->setData(self::STORE_NAME, $storeName);
    }

    /**
     * Get website configuration
     *
     * @return WebsiteConfigurationInterface
     */
    public function getWebsiteConfiguration(): WebsiteConfigurationInterface
    {
        return $this->getData(self::WEBSITE_CONFIGURATION);
    }

    /**
     * Set website configuration
     *
     * @param WebsiteConfigurationInterface $websiteConfiguration
     * @return StoreInfoInterface
     */
    public function setWebsiteConfiguration(WebsiteConfigurationInterface $websiteConfiguration): StoreInfoInterface
    {
        return $this->setData(self::WEBSITE_CONFIGURATION, $websiteConfiguration);
    }
}
