<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Diagnostic;

/**
 * Main diagnostic data interface
 */
interface DiagnosticDataInterface
{
    public const SUCCESS = 'success';
    public const ERROR = 'error';
    public const TIMESTAMP = 'timestamp';
    public const PLATFORM = 'platform';
    public const STORE_INFO = 'store_info';
    public const BOLD_CONFIG = 'bold_config';
    /**
     * Get success
     *
     * @return bool
     */
    public function getSuccess(): bool;

    /**
     * Set success
     *
     * @param bool $success
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\DiagnosticDataInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setSuccess(bool $success): DiagnosticDataInterface;

    /**
     * Get error
     *
     * @return string|null
     */
    public function getError(): ?string;

    /**
     * Set error
     *
     * @param string|null $error
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\DiagnosticDataInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setError(?string $error): DiagnosticDataInterface;

    /**
     * Get timestamp
     *
     * @return string
     */
    public function getTimestamp(): string;

    /**
     * Set timestamp
     *
     * @param string $timestamp
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\DiagnosticDataInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setTimestamp(string $timestamp): DiagnosticDataInterface;

    /**
     * Get system info
     *
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\PlatformInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function getPlatform(): PlatformInterface;

    /**
     * Set platform
     *
     * @param \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\PlatformInterface $platform
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\DiagnosticDataInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setPlatform(PlatformInterface $platform): DiagnosticDataInterface;

    /**
     * Get store info
     *
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\StoreInfoInterface[]
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function getStoreInfo(): array;

    /**
     * Set store info
     *
     * @param \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\StoreInfoInterface[] $storeInfo
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\DiagnosticDataInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setStoreInfo(array $storeInfo): DiagnosticDataInterface;

    /**
     * Get bold config
     *
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\BoldConfigInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function getBoldConfig(): BoldConfigInterface;

    /**
     * Set bold config
     *
     * @param \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\BoldConfigInterface $boldConfig
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\DiagnosticDataInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setBoldConfig(BoldConfigInterface $boldConfig): DiagnosticDataInterface;


}
