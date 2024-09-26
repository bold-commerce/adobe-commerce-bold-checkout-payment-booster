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
    public const PATH_SHOP_ID = 'checkout/bold_checkout_payment_booster/shop_id';
    private const PATH_IS_PAYMENT_BOOSTER_ENABLED = 'checkout/bold_checkout_payment_booster/is_payment_booster_enabled';
    private const PATH_IS_FASTLANE_ENABLED = 'checkout/bold_checkout_payment_booster/is_fastlane_enabled';
    private const PATH_INTEGRATION_API_URL = 'checkout/bold_checkout_payment_booster_advanced/api_url';
    private const PATH_LOG_IS_ENABLED = 'checkout/bold_checkout_payment_booster_advanced/log_enabled';
    private const PATH_SHARED_SECRET = 'checkout/bold_checkout_payment_booster/shared_secret';
    private const PATH_PAYMENT_BOOSTER_FLOW_ID = 'checkout/bold_checkout_payment_booster/default_flow_id';

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
    public function setPaymentBoosterFlowID(int $websiteId, ?string $flowID): void
    {
        $this->configWriter->save(
            self::PATH_PAYMENT_BOOSTER_FLOW_ID,
            $flowID,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }

    public function getPaymentBoosterFlowID(int $websiteId): ?string
    {
        return $this->scopeConfig->getValue(
            self::PATH_PAYMENT_BOOSTER_FLOW_ID,
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        );
    }
}
