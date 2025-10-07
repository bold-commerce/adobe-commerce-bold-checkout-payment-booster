<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Data\Diagnostic;

use Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\BoldConfigInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\ValidationsInterface;
use Magento\Framework\DataObject;

/**
 * Bold configuration data model
 */
class BoldConfig extends DataObject implements BoldConfigInterface
{
    /**
     * Get shop ID
     *
     * @return string
     */
    public function getShopId(): string
    {
        return (string) $this->getData(self::SHOP_ID);
    }

    /**
     * Set shop ID
     *
     * @param string $shopId
     * @return BoldConfigInterface
     */
    public function setShopId(string $shopId): BoldConfigInterface
    {
        return $this->setData(self::SHOP_ID, $shopId);
    }

    /**
     * Get bold booster flow ID
     *
     * @return string
     */
    public function getBoldBoosterFlowId(): string
    {
        return (string) $this->getData(self::BOLD_BOOSTER_FLOW_ID);
    }

    /**
     * Set bold booster flow ID
     *
     * @param string $boldBoosterFlowId
     * @return BoldConfigInterface
     */
    public function setBoldBoosterFlowId(string $boldBoosterFlowId): BoldConfigInterface
    {
        return $this->setData(self::BOLD_BOOSTER_FLOW_ID, $boldBoosterFlowId);
    }

    /**
     * Get configuration group label
     *
     * @return string|null
     */
    public function getConfigurationGroupLabel(): ?string
    {
        return $this->getData(self::CONFIGURATION_GROUP_LABEL);
    }

    /**
     * Set configuration group label
     *
     * @param string|null $configurationGroupLabel
     * @return BoldConfigInterface
     */
    public function setConfigurationGroupLabel(?string $configurationGroupLabel): BoldConfigInterface
    {
        return $this->setData(self::CONFIGURATION_GROUP_LABEL, $configurationGroupLabel);
    }

    /**
     * Get is cart wallet pay enabled
     *
     * @return bool
     */
    public function getIsCartWalletPayEnabled(): bool
    {
        return (bool) $this->getData(self::IS_CART_WALLET_PAY_ENABLED);
    }

    /**
     * Set is cart wallet pay enabled
     *
     * @param bool $isCartWalletPayEnabled
     * @return BoldConfigInterface
     */
    public function setIsCartWalletPayEnabled(bool $isCartWalletPayEnabled): BoldConfigInterface
    {
        return $this->setData(self::IS_CART_WALLET_PAY_ENABLED, $isCartWalletPayEnabled);
    }

    /**
     * Get is express pay enabled
     *
     * @return bool
     */
    public function getIsExpressPayEnabled(): bool
    {
        return (bool) $this->getData(self::IS_EXPRESS_PAY_ENABLED);
    }

    /**
     * Set is express pay enabled
     *
     * @param bool $isExpressPayEnabled
     * @return BoldConfigInterface
     */
    public function setIsExpressPayEnabled(bool $isExpressPayEnabled): BoldConfigInterface
    {
        return $this->setData(self::IS_EXPRESS_PAY_ENABLED, $isExpressPayEnabled);
    }

    /**
     * Get is fastlane enabled
     *
     * @return bool
     */
    public function getIsFastlaneEnabled(): bool
    {
        return (bool) $this->getData(self::IS_FASTLANE_ENABLED);
    }

    /**
     * Set is fastlane enabled
     *
     * @param bool $isFastlaneEnabled
     * @return BoldConfigInterface
     */
    public function setIsFastlaneEnabled(bool $isFastlaneEnabled): BoldConfigInterface
    {
        return $this->setData(self::IS_FASTLANE_ENABLED, $isFastlaneEnabled);
    }

    /**
     * Get is payment booster enabled
     *
     * @return bool
     */
    public function getIsPaymentBoosterEnabled(): bool
    {
        return (bool) $this->getData(self::IS_PAYMENT_BOOSTER_ENABLED);
    }

    /**
     * Set is payment booster enabled
     *
     * @param bool $isPaymentBoosterEnabled
     * @return BoldConfigInterface
     */
    public function setIsPaymentBoosterEnabled(bool $isPaymentBoosterEnabled): BoldConfigInterface
    {
        return $this->setData(self::IS_PAYMENT_BOOSTER_ENABLED, $isPaymentBoosterEnabled);
    }

    /**
     * Get is product wallet pay enabled
     *
     * @return bool
     */
    public function getIsProductWalletPayEnabled(): bool
    {
        return (bool) $this->getData(self::IS_PRODUCT_WALLET_PAY_ENABLED);
    }

    /**
     * Set is product wallet pay enabled
     *
     * @param bool $isProductWalletPayEnabled
     * @return BoldConfigInterface
     */
    public function setIsProductWalletPayEnabled(bool $isProductWalletPayEnabled): BoldConfigInterface
    {
        return $this->setData(self::IS_PRODUCT_WALLET_PAY_ENABLED, $isProductWalletPayEnabled);
    }

    /**
     * Get static EPS URL
     *
     * @return string
     */
    public function getStaticEpsUrl(): string
    {
        return (string) $this->getData(self::STATIC_EPS_URL);
    }

    /**
     * Set static EPS URL
     *
     * @param string $staticEpsUrl
     * @return BoldConfigInterface
     */
    public function setStaticEpsUrl(string $staticEpsUrl): BoldConfigInterface
    {
        return $this->setData(self::STATIC_EPS_URL, $staticEpsUrl);
    }

    /**
     * Get log enabled
     *
     * @return bool
     */
    public function getLogEnabled(): bool
    {
        return (bool) $this->getData(self::LOG_ENABLED);
    }

    /**
     * Set log enabled
     *
     * @param bool $logEnabled
     * @return BoldConfigInterface
     */
    public function setLogEnabled(bool $logEnabled): BoldConfigInterface
    {
        return $this->setData(self::LOG_ENABLED, $logEnabled);
    }

    /**
     * Get EPS URL
     *
     * @return string
     */
    public function getEpsUrl(): string
    {
        return (string) $this->getData(self::EPS_URL);
    }

    /**
     * Set EPS URL
     *
     * @param string $epsUrl
     * @return BoldConfigInterface
     */
    public function setEpsUrl(string $epsUrl): BoldConfigInterface
    {
        return $this->setData(self::EPS_URL, $epsUrl);
    }

    /**
     * Get API URL
     *
     * @return string
     */
    public function getApiUrl(): string
    {
        return (string) $this->getData(self::API_URL);
    }

    /**
     * Set API URL
     *
     * @param string $apiUrl
     * @return BoldConfigInterface
     */
    public function setApiUrl(string $apiUrl): BoldConfigInterface
    {
        return $this->setData(self::API_URL, $apiUrl);
    }

    /**
     * Get fastlane payment title
     *
     * @return string
     */
    public function getFastlanePaymentTitle(): string
    {
        return (string) $this->getData(self::FASTLANE_PAYMENT_TITLE);
    }

    /**
     * Set fastlane payment title
     *
     * @param string $fastlanePaymentTitle
     * @return BoldConfigInterface
     */
    public function setFastlanePaymentTitle(string $fastlanePaymentTitle): BoldConfigInterface
    {
        return $this->setData(self::FASTLANE_PAYMENT_TITLE, $fastlanePaymentTitle);
    }

    /**
     * Get wallet payment title
     *
     * @return string
     */
    public function getWalletPaymentTitle(): string
    {
        return (string) $this->getData(self::WALLET_PAYMENT_TITLE);
    }

    /**
     * Set wallet payment title
     *
     * @param string $walletPaymentTitle
     * @return BoldConfigInterface
     */
    public function setWalletPaymentTitle(string $walletPaymentTitle): BoldConfigInterface
    {
        return $this->setData(self::WALLET_PAYMENT_TITLE, $walletPaymentTitle);
    }

    /**
     * Get payment title
     *
     * @return string
     */
    public function getPaymentTitle(): string
    {
        return (string) $this->getData(self::PAYMENT_TITLE);
    }

    /**
     * Set payment title
     *
     * @param string $paymentTitle
     * @return BoldConfigInterface
     */
    public function setPaymentTitle(string $paymentTitle): BoldConfigInterface
    {
        return $this->setData(self::PAYMENT_TITLE, $paymentTitle);
    }

    /**
     * Get enable sales order view tab
     *
     * @return bool
     */
    public function getEnableSalesOrderViewTab(): bool
    {
        return (bool) $this->getData(self::ENABLE_SALES_ORDER_VIEW_TAB);
    }

    /**
     * Set enable sales order view tab
     *
     * @param bool $enableSalesOrderViewTab
     * @return BoldConfigInterface
     */
    public function setEnableSalesOrderViewTab(bool $enableSalesOrderViewTab): BoldConfigInterface
    {
        return $this->setData(self::ENABLE_SALES_ORDER_VIEW_TAB, $enableSalesOrderViewTab);
    }

    /**
     * Get validations
     *
     * @return ValidationsInterface
     */
    public function getValidations(): ValidationsInterface
    {
        return $this->getData(self::VALIDATIONS);
    }

    /**
     * Set validations
     *
     * @param ValidationsInterface $validations
     * @return BoldConfigInterface
     */
    public function setValidations(ValidationsInterface $validations): BoldConfigInterface
    {
        return $this->setData(self::VALIDATIONS, $validations);
    }
}
