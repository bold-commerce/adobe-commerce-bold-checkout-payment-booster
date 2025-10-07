<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Diagnostic;

interface ValidationsInterface
{
    public const CONFIGURED = 'configured';
    public const API_URL_CONFIGURED = 'api_url_configured';
    public const SHOP_ID_CONFIGURED = 'shop_id_configured';
    public const STATIC_EPS_CONFIGURED = 'static_eps_configured';
    public const EPS_CONFIGURED = 'eps_configured';
    public const TEST_REQUEST_SUCCESSFUL = 'test_request_successful';
    /**
     * Get configured
     *
     * @return bool
     */
    public function getConfigured(): bool;

    /**
     * Set configured
     *
     * @param bool $configured
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\ValidationsInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setConfigured(bool $configured): ValidationsInterface;

    /**
     * Get API URL configured
     *
     * @return bool
     */
    public function getApiUrlConfigured(): bool;

    /**
     * Set API URL configured
     *
     * @param bool $apiUrlConfigured
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\ValidationsInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setApiUrlConfigured(bool $apiUrlConfigured): ValidationsInterface;

    /**
     * Get shop ID configured
     *
     * @return bool
     */
    public function getShopIdConfigured(): bool;

    /**
     * Set shop ID configured
     *
     * @param bool $shopIdConfigured
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\ValidationsInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setShopIdConfigured(bool $shopIdConfigured): ValidationsInterface;

    /**
     * Get static EPS configured
     *
     * @return bool
     */
    public function getStaticEpsConfigured(): bool;

    /**
     * Set static EPS configured
     *
     * @param bool $staticEpsConfigured
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\ValidationsInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setStaticEpsConfigured(bool $staticEpsConfigured): ValidationsInterface;

    /**
     * Get EPS configured
     *
     * @return bool
     */
    public function getEpsConfigured(): bool;

    /**
     * Set EPS configured
     *
     * @param bool $epsConfigured
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\ValidationsInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setEpsConfigured(bool $epsConfigured): ValidationsInterface;

    /**
     * Get test request successful
     *
     * @return bool
     */
    public function getTestRequestSuccessful(): bool;

    /**
     * Set test request successful
     *
     * @param bool $testRequestSuccessful
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\ValidationsInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setTestRequestSuccessful(bool $testRequestSuccessful): ValidationsInterface;
}
