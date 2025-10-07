<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Diagnostic;

interface PlatformInterface
{
    public const PLATFORM_VERSION = 'platform_version';
    public const VERSION = 'version';
    public const INSTALL_PATH = 'install_path';
    /**
     * Get platform version
     *
     * @return string
     */
    public function getPlatformVersion(): string;

    /**
     * Set platform version
     *
     * @param string $platformVersion
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\PlatformInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setPlatformVersion(string $platformVersion): PlatformInterface;

    /**
     * Get version
     *
     * @return string
     */
    public function getVersion(): string;

    /**
     * Set version
     *
     * @param string $version
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\PlatformInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setVersion(string $version): PlatformInterface;

    /**
     * Get install path
     *
     * @return string
     */
    public function getInstallPath(): string;

    /**
     * Set the installation path
     *
     * @param string $installPath
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\PlatformInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setInstallPath(string $installPath): PlatformInterface;
}
