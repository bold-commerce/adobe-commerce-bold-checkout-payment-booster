<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Data\Diagnostic;

use Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\DiagnosticDataInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\PlatformInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\StoreInfoInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\BoldConfigInterface;
use Magento\Framework\DataObject;

/**
 * Main diagnostic data model
 */
class DiagnosticData extends DataObject implements DiagnosticDataInterface
{
    /**
     * Get success
     *
     * @return bool
     */
    public function getSuccess(): bool
    {
        return (bool) $this->getData(self::SUCCESS);
    }

    /**
     * Set success
     *
     * @param bool $success
     * @return DiagnosticDataInterface
     */
    public function setSuccess(bool $success): DiagnosticDataInterface
    {
        return $this->setData(self::SUCCESS, $success);
    }

    /**
     * Get error
     *
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->getData(self::ERROR);
    }

    /**
     * Set error
     *
     * @param string|null $error
     * @return DiagnosticDataInterface
     */
    public function setError(?string $error): DiagnosticDataInterface
    {
        return $this->setData(self::ERROR, $error);
    }

    /**
     * Get timestamp
     *
     * @return string
     */
    public function getTimestamp(): string
    {
        return (string) $this->getData(self::TIMESTAMP);
    }

    /**
     * Set timestamp
     *
     * @param string $timestamp
     * @return DiagnosticDataInterface
     */
    public function setTimestamp(string $timestamp): DiagnosticDataInterface
    {
        return $this->setData(self::TIMESTAMP, $timestamp);
    }

    /**
     * Get platform
     *
     * @return PlatformInterface
     */
    public function getPlatform(): PlatformInterface
    {
        return $this->getData(self::PLATFORM);
    }

    /**
     * Set platform
     *
     * @param PlatformInterface $platform
     * @return DiagnosticDataInterface
     */
    public function setPlatform(PlatformInterface $platform): DiagnosticDataInterface
    {
        return $this->setData(self::PLATFORM, $platform);
    }

    /**
     * Get store info
     *
     * @return StoreInfoInterface[]
     */
    public function getStoreInfo(): array
    {
        return (array) $this->getData(self::STORE_INFO);
    }

    /**
     * Set store info
     *
     * @param StoreInfoInterface[] $storeInfo
     * @return DiagnosticDataInterface
     */
    public function setStoreInfo(array $storeInfo): DiagnosticDataInterface
    {
        return $this->setData(self::STORE_INFO, $storeInfo);
    }

    /**
     * Get bold config
     *
     * @return BoldConfigInterface
     */
    public function getBoldConfig(): BoldConfigInterface
    {
        return $this->getData(self::BOLD_CONFIG);
    }

    /**
     * Set bold config
     *
     * @param BoldConfigInterface $boldConfig
     * @return DiagnosticDataInterface
     */
    public function setBoldConfig(BoldConfigInterface $boldConfig): DiagnosticDataInterface
    {
        return $this->setData(self::BOLD_CONFIG, $boldConfig);
    }


}
