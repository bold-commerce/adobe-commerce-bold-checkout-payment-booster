<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Data\Diagnostic;

use Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\ValidationsInterface;
use Magento\Framework\DataObject;

/**
 * Validations data model
 */
class Validations extends DataObject implements ValidationsInterface
{
    /**
     * Get configured
     *
     * @return bool
     */
    public function getConfigured(): bool
    {
        return (bool) $this->getData(self::CONFIGURED);
    }

    /**
     * Set configured
     *
     * @param bool $configured
     * @return ValidationsInterface
     */
    public function setConfigured(bool $configured): ValidationsInterface
    {
        return $this->setData(self::CONFIGURED, $configured);
    }

    /**
     * Get API URL configured
     *
     * @return bool
     */
    public function getApiUrlConfigured(): bool
    {
        return (bool) $this->getData(self::API_URL_CONFIGURED);
    }

    /**
     * Set API URL configured
     *
     * @param bool $apiUrlConfigured
     * @return ValidationsInterface
     */
    public function setApiUrlConfigured(bool $apiUrlConfigured): ValidationsInterface
    {
        return $this->setData(self::API_URL_CONFIGURED, $apiUrlConfigured);
    }

    /**
     * Get shop ID configured
     *
     * @return bool
     */
    public function getShopIdConfigured(): bool
    {
        return (bool) $this->getData(self::SHOP_ID_CONFIGURED);
    }

    /**
     * Set shop ID configured
     *
     * @param bool $shopIdConfigured
     * @return ValidationsInterface
     */
    public function setShopIdConfigured(bool $shopIdConfigured): ValidationsInterface
    {
        return $this->setData(self::SHOP_ID_CONFIGURED, $shopIdConfigured);
    }

    /**
     * Get static EPS configured
     *
     * @return bool
     */
    public function getStaticEpsConfigured(): bool
    {
        return (bool) $this->getData(self::STATIC_EPS_CONFIGURED);
    }

    /**
     * Set static EPS configured
     *
     * @param bool $staticEpsConfigured
     * @return ValidationsInterface
     */
    public function setStaticEpsConfigured(bool $staticEpsConfigured): ValidationsInterface
    {
        return $this->setData(self::STATIC_EPS_CONFIGURED, $staticEpsConfigured);
    }

    /**
     * Get EPS configured
     *
     * @return bool
     */
    public function getEpsConfigured(): bool
    {
        return (bool) $this->getData(self::EPS_CONFIGURED);
    }

    /**
     * Set EPS configured
     *
     * @param bool $epsConfigured
     * @return ValidationsInterface
     */
    public function setEpsConfigured(bool $epsConfigured): ValidationsInterface
    {
        return $this->setData(self::EPS_CONFIGURED, $epsConfigured);
    }

    /**
     * Get test request successful
     *
     * @return bool
     */
    public function getTestRequestSuccessful(): bool
    {
        return (bool) $this->getData(self::TEST_REQUEST_SUCCESSFUL);
    }

    /**
     * Set test request successful
     *
     * @param bool $testRequestSuccessful
     * @return ValidationsInterface
     */
    public function setTestRequestSuccessful(bool $testRequestSuccessful): ValidationsInterface
    {
        return $this->setData(self::TEST_REQUEST_SUCCESSFUL, $testRequestSuccessful);
    }
}
