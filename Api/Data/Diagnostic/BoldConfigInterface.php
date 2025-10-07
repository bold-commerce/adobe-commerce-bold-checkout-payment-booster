<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Diagnostic;

/**
 * Bold configuration interface
 */
interface BoldConfigInterface
{
    public const SHOP_ID = 'shop_id';
    public const BOLD_BOOSTER_FLOW_ID = 'bold_booster_flow_id';
    public const CONFIGURATION_GROUP_LABEL = 'configuration_group_label';
    public const IS_CART_WALLET_PAY_ENABLED = 'is_cart_wallet_pay_enabled';
    public const IS_EXPRESS_PAY_ENABLED = 'is_express_pay_enabled';
    public const IS_FASTLANE_ENABLED = 'is_fastlane_enabled';
    public const IS_PAYMENT_BOOSTER_ENABLED = 'is_payment_booster_enabled';
    public const IS_PRODUCT_WALLET_PAY_ENABLED = 'is_product_wallet_pay_enabled';
    public const STATIC_EPS_URL = 'static_eps_url';
    public const LOG_ENABLED = 'log_enabled';
    public const EPS_URL = 'eps_url';
    public const API_URL = 'api_url';
    public const FASTLANE_PAYMENT_TITLE = 'fastlane_payment_title';
    public const WALLET_PAYMENT_TITLE = 'wallet_payment_title';
    public const PAYMENT_TITLE = 'payment_title';
    public const ENABLE_SALES_ORDER_VIEW_TAB = 'enable_sales_order_view_tab';
    public const VALIDATIONS = 'validations';
    /**
     * Get shop ID
     *
     * @return string
     */
    public function getShopId(): string;

    /**
     * Set shop ID
     *
     * @param string $shopId
     * @return BoldConfigInterface
     */
    public function setShopId(string $shopId): BoldConfigInterface;

    /**
     * Get bold booster flow ID
     *
     * @return string
     */
    public function getBoldBoosterFlowId(): string;

    /**
     * Set bold booster flow ID
     *
     * @param string $boldBoosterFlowId
     * @return BoldConfigInterface
     */
    public function setBoldBoosterFlowId(string $boldBoosterFlowId): BoldConfigInterface;

    /**
     * Get configuration group label
     *
     * @return string|null
     */
    public function getConfigurationGroupLabel(): ?string;

    /**
     * Set configuration group label
     *
     * @param string|null $configurationGroupLabel
     * @return BoldConfigInterface
     */
    public function setConfigurationGroupLabel(?string $configurationGroupLabel): BoldConfigInterface;

    /**
     * Get is cart wallet pay enabled
     *
     * @return bool
     */
    public function getIsCartWalletPayEnabled(): bool;

    /**
     * Set is cart wallet pay enabled
     *
     * @param bool $isCartWalletPayEnabled
     * @return BoldConfigInterface
     */
    public function setIsCartWalletPayEnabled(bool $isCartWalletPayEnabled): BoldConfigInterface;

    /**
     * Get is express pay enabled
     *
     * @return bool
     */
    public function getIsExpressPayEnabled(): bool;

    /**
     * Set is express pay enabled
     *
     * @param bool $isExpressPayEnabled
     * @return BoldConfigInterface
     */
    public function setIsExpressPayEnabled(bool $isExpressPayEnabled): BoldConfigInterface;

    /**
     * Get is fastlane enabled
     *
     * @return bool
     */
    public function getIsFastlaneEnabled(): bool;

    /**
     * Set is fastlane enabled
     *
     * @param bool $isFastlaneEnabled
     * @return BoldConfigInterface
     */
    public function setIsFastlaneEnabled(bool $isFastlaneEnabled): BoldConfigInterface;

    /**
     * Get is payment booster enabled
     *
     * @return bool
     */
    public function getIsPaymentBoosterEnabled(): bool;

    /**
     * Set is payment booster enabled
     *
     * @param bool $isPaymentBoosterEnabled
     * @return BoldConfigInterface
     */
    public function setIsPaymentBoosterEnabled(bool $isPaymentBoosterEnabled): BoldConfigInterface;

    /**
     * Get is product wallet pay enabled
     *
     * @return bool
     */
    public function getIsProductWalletPayEnabled(): bool;

    /**
     * Set is product wallet pay enabled
     *
     * @param bool $isProductWalletPayEnabled
     * @return BoldConfigInterface
     */
    public function setIsProductWalletPayEnabled(bool $isProductWalletPayEnabled): BoldConfigInterface;

    /**
     * Get static EPS URL
     *
     * @return string
     */
    public function getStaticEpsUrl(): string;

    /**
     * Set static EPS URL
     *
     * @param string $staticEpsUrl
     * @return BoldConfigInterface
     */
    public function setStaticEpsUrl(string $staticEpsUrl): BoldConfigInterface;

    /**
     * Get log enabled
     *
     * @return bool
     */
    public function getLogEnabled(): bool;

    /**
     * Set log enabled
     *
     * @param bool $logEnabled
     * @return BoldConfigInterface
     */
    public function setLogEnabled(bool $logEnabled): BoldConfigInterface;

    /**
     * Get EPS URL
     *
     * @return string
     */
    public function getEpsUrl(): string;

    /**
     * Set EPS URL
     *
     * @param string $epsUrl
     * @return BoldConfigInterface
     */
    public function setEpsUrl(string $epsUrl): BoldConfigInterface;

    /**
     * Get API URL
     *
     * @return string
     */
    public function getApiUrl(): string;

    /**
     * Set API URL
     *
     * @param string $apiUrl
     * @return BoldConfigInterface
     */
    public function setApiUrl(string $apiUrl): BoldConfigInterface;

    /**
     * Get fastlane payment title
     *
     * @return string
     */
    public function getFastlanePaymentTitle(): string;

    /**
     * Set fastlane payment title
     *
     * @param string $fastlanePaymentTitle
     * @return BoldConfigInterface
     */
    public function setFastlanePaymentTitle(string $fastlanePaymentTitle): BoldConfigInterface;

    /**
     * Get wallet payment title
     *
     * @return string
     */
    public function getWalletPaymentTitle(): string;

    /**
     * Set wallet payment title
     *
     * @param string $walletPaymentTitle
     * @return BoldConfigInterface
     */
    public function setWalletPaymentTitle(string $walletPaymentTitle): BoldConfigInterface;

    /**
     * Get payment title
     *
     * @return string
     */
    public function getPaymentTitle(): string;

    /**
     * Set payment title
     *
     * @param string $paymentTitle
     * @return BoldConfigInterface
     */
    public function setPaymentTitle(string $paymentTitle): BoldConfigInterface;

    /**
     * Get enable sales order view tab
     *
     * @return bool
     */
    public function getEnableSalesOrderViewTab(): bool;

    /**
     * Set enable sales order view tab
     *
     * @param bool $enableSalesOrderViewTab
     * @return BoldConfigInterface
     */
    public function setEnableSalesOrderViewTab(bool $enableSalesOrderViewTab): BoldConfigInterface;

    /**
     * Get validations
     *
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\ValidationsInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function getValidations(): ValidationsInterface;

    /**
     * Set validations
     *
     * @param \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\ValidationsInterface $validations
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\BoldConfigInterface
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function setValidations(ValidationsInterface $validations): BoldConfigInterface;
}
