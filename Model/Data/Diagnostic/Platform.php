<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Data\Diagnostic;

use Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\PlatformInterface;
use Magento\Framework\DataObject;

/**
 * Platform data model
 */
class Platform extends DataObject implements PlatformInterface
{
    /**
     * Get platform version
     *
     * @return string
     */
    public function getPlatformVersion(): string
    {
        return (string) $this->getData(self::PLATFORM_VERSION);
    }

    /**
     * Set platform version
     *
     * @param string $platformVersion
     * @return PlatformInterface
     */
    public function setPlatformVersion(string $platformVersion): PlatformInterface
    {
        return $this->setData(self::PLATFORM_VERSION, $platformVersion);
    }

    /**
     * Get version
     *
     * @return string
     */
    public function getVersion(): string
    {
        return (string) $this->getData(self::VERSION);
    }

    /**
     * Set version
     *
     * @param string $version
     * @return PlatformInterface
     */
    public function setVersion(string $version): PlatformInterface
    {
        return $this->setData(self::VERSION, $version);
    }

    /**
     * Get install path
     *
     * @return string
     */
    public function getInstallPath(): string
    {
        return (string) $this->getData(self::INSTALL_PATH);
    }

    /**
     * Set the installation path
     *
     * @param string $installPath
     * @return PlatformInterface
     */
    public function setInstallPath(string $installPath): PlatformInterface
    {
        return $this->setData(self::INSTALL_PATH, $installPath);
    }
}
