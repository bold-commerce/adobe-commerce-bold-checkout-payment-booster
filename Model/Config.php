<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Module config.
 */
class Config
{
    private const PATH_TOKEN = 'checkout/bold_checkout_payment_booster/api_token';
    public const PATH_PAYMENT_CSS = 'checkout/bold_checkout_custom_elements/payment_css';
    public const PATH_PAYMENT_TITLE = 'checkout/bold_checkout_payment_booster/payment_title';
    public const PATH_FASTLANE_PAYMENT_TITLE = 'checkout/bold_checkout_payment_booster/fastlane_payment_title';
    public const PATH_WALLET_PAYMENT_TITLE = 'checkout/bold_checkout_payment_booster/wallet_payment_title';
    public const PATH_SHOP_ID = 'checkout/bold_checkout_payment_booster/shop_id';
    private const PATH_IS_PAYMENT_BOOSTER_ENABLED = 'checkout/bold_checkout_payment_booster/is_payment_booster_enabled';
    private const PATH_IS_FASTLANE_ENABLED = 'checkout/bold_checkout_payment_booster/is_fastlane_enabled';
    private const PATH_INTEGRATION_API_URL = 'checkout/bold_checkout_payment_booster_advanced/api_url';
    private const PATH_EPS_URL = 'checkout/bold_checkout_payment_booster_advanced/eps_url';
    private const PATH_STATIC_EPS_URL = 'checkout/bold_checkout_payment_booster_advanced/static_eps_url';
    private const PATH_LOG_IS_ENABLED = 'checkout/bold_checkout_payment_booster_advanced/log_enabled';
    private const PATH_SHARED_SECRET = 'checkout/bold_checkout_payment_booster/shared_secret';
    private const PATH_CONFIGURATION_GROUP_LABEL = 'checkout/bold_checkout_payment_booster/configuration_group_label';
    private const PATH_BOLD_BOOSTER_FLOW_ID = 'checkout/bold_checkout_payment_booster/bold_booster_flow_id';
    private const PATH_IS_EXPRESS_PAY_ENABLED = 'checkout/bold_checkout_payment_booster/is_express_pay_enabled';
    private const PATH_IS_CART_WALLET_PAY_ENABLED = 'checkout/bold_checkout_payment_booster/is_cart_wallet_pay_enabled';
    private const PATH_IS_PRODUCT_WALLET_PAY_ENABLED = 'checkout/bold_checkout_payment_booster/is_product_wallet_pay_enabled';
    private const PATH_IS_TAX_INCLUDED_IN_PRICES = 'tax/calculation/price_includes_tax';
    private const PATH_IS_TAX_INCLUDED_IN_SHIPPING = 'tax/calculation/shipping_includes_tax';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     * @param TypeListInterface $cacheTypeList
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList,
        EncryptorInterface $encryptor
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
        $this->encryptor = $encryptor;
    }

    /**
     * @param int $websiteId
     * @return string|null
     */
    public function getApiUrl(int $websiteId): ?string
    {
        return $this->scopeConfig->getValue(
            self::PATH_INTEGRATION_API_URL,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    /**
     * Get EPS base url.
     *
     * @param int $websiteId
     * @return string|null
     */
    public function getEpsUrl(int $websiteId): ?string
    {
        return $this->scopeConfig->getValue(
            self::PATH_EPS_URL,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    /**
     * Get static EPS base url.
     *
     * @param int $websiteId
     * @return string|null
     */
    public function getStaticEpsUrl(int $websiteId): ?string
    {
        return $this->scopeConfig->getValue(
            self::PATH_STATIC_EPS_URL,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    /**
     * Check if the Payment Booster is enabled.
     *
     * @param int $websiteId
     * @return bool
     */
    public function isPaymentBoosterEnabled(int $websiteId): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::PATH_IS_PAYMENT_BOOSTER_ENABLED,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    /**
     * Check if the Fastlane is enabled.
     *
     * @param int $websiteId
     * @return bool
     */
    public function isFastlaneEnabled(int $websiteId): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::PATH_IS_FASTLANE_ENABLED,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    /**
     * Get payment iframe additional css.
     *
     * @param int $websiteId
     * @return string
     */
    public function getPaymentCss(int $websiteId): string
    {
        return (string)$this->scopeConfig->getValue(
            self::PATH_PAYMENT_CSS,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    /**
     * Save Bold shop id to config.
     *
     * @param int $websiteId
     * @return void
     */
    public function setShopId(int $websiteId, ?string $shopId): void
    {
        $this->configWriter->save(
            self::PATH_SHOP_ID,
            $shopId,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
        $this->cacheTypeList->cleanType('config');
        $this->scopeConfig->clean();
    }

    /**
     * Get Bold Shop Id.
     *
     * @param int $websiteId
     * @return string|null
     */
    public function getShopId(int $websiteId): ?string
    {
        return $this->scopeConfig->getValue(
            self::PATH_SHOP_ID,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    /**
     * Check if logging is enabled.
     *
     * @param int $websiteId
     * @return bool
     */
    public function getLogIsEnabled(int $websiteId): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::PATH_LOG_IS_ENABLED,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    /**
     * Get saved Bold API token.
     *
     * @param int $websiteId
     * @return void
     */
    public function getApiToken(int $websiteId): ?string
    {
        $encryptedToken = $this->scopeConfig->getValue(
            self::PATH_TOKEN,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );

        return $this->encryptor->decrypt($encryptedToken);
    }

    /**
     * Get decrypted Bold API shared secret.
     *
     * @param int $websiteId
     * @return string|null
     */
    public function getSharedSecret(int $websiteId): ?string
    {
        $encryptedToken = $this->scopeConfig->getValue(
            self::PATH_SHARED_SECRET,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );

        return $this->encryptor->decrypt($encryptedToken);
    }

    /**
     * Set encrypted Bold API shared secret.
     *
     * @param int $websiteId
     * @param string|null $sharedSecret
     * @return void
     */
    public function setSharedSecret(int $websiteId, ?string $sharedSecret): void
    {
        $encryptedToken = $this->encryptor->encrypt($sharedSecret);
        $this->configWriter->save(
            self::PATH_SHARED_SECRET,
            $encryptedToken,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    /**
     * Set Bold Booster Flow ID.
     *
     * @param int $websiteId
     * @param string $flowID
     * @return void
     */
    public function setBoldBoosterFlowID(int $websiteId, string $flowID): void
    {
        $this->configWriter->save(
            self::PATH_BOLD_BOOSTER_FLOW_ID,
            $flowID,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    /**
     * Get Bold Booster Flow ID.
     *
     * @param int $websiteId
     * @return string|null
     */
    public function getBoldBoosterFlowID(int $websiteId): ?string
    {
        return $this->scopeConfig->getValue(
            self::PATH_BOLD_BOOSTER_FLOW_ID,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    /**
     * Get EPS configuration group label.
     *
     * @param int $websiteId
     * @return string|null
     */
    public function getConfigurationGroupLabel(int $websiteId): ?string
    {
        return $this->scopeConfig->getValue(
            self::PATH_CONFIGURATION_GROUP_LABEL,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    /**
     * Check if Express Pay buttons are enabled.
     *
     * @param int $websiteId
     * @return bool
     */
    public function isExpressPayEnabled(int $websiteId): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::PATH_IS_EXPRESS_PAY_ENABLED,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }    
    
    /**
     * Check if Wallet Express Pay buttons are enabled On the cart and mini cart pages.
     *
     * @param int $websiteId
     * @return bool
     */
    public function isCartWalletPayEnabled(int $websiteId): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::PATH_IS_CART_WALLET_PAY_ENABLED,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }
    
    /**
     * Check if Wallet Express Pay buttons are enabled on the product pages.
     *
     * @param int $websiteId
     * @return bool
     */
    public function isProductWalletPayEnabled(int $websiteId): bool
    {
        return false;
        
        // $this->scopeConfig->isSetFlag(
        // self::PATH_IS_PRODUCT_WALLET_PAY_ENABLED,
        // ScopeInterface::SCOPE_WEBSITES,
        // $websiteId
        // );
    }

    /**
     * Check if tax is included in item prices.
     *
     * @param int $websiteId
     * @return bool
     */
    public function isTaxIncludedInPrices(int $websiteId): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::PATH_IS_TAX_INCLUDED_IN_PRICES,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    /**
     * Check if tax is included in shipping cost.
     *
     * @param int $websiteId
     * @return bool
     */
    public function isTaxIncludedInShipping(int $websiteId): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::PATH_IS_TAX_INCLUDED_IN_SHIPPING,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }
}
