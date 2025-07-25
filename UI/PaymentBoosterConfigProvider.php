<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\UI;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Service;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Config\Model\Config\Source\Nooptreq as NooptreqSource;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\Country;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Config provider for Payment Booster.
 */
class PaymentBoosterConfigProvider implements ConfigProviderInterface
{
    public const CONTAINER_PREFIX = 'express-pay-buttons-';
    public const PAGE_SOURCE_PRODUCT = 'product-details';
    public const PAGE_SOURCE_CART = 'cart';
    public const PAGE_SOURCE_MINICART = 'mini-cart';
    public const PAGE_SOURCE_CHECKOUT = 'checkout';

    /**
     * @var CheckoutData
     */
    private $checkoutData;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var AllowedCountries
     */
    private $allowedCountries;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array<array{
     *     value: string,
     *     label: string,
     *     is_region_required?: bool,
     *     is_region_visible?: bool,
     *     is_zipcode_optional?: bool
     * }>
     */
    private $countries = [];

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * @param CheckoutData $checkoutData
     * @param Config $config
     * @param AllowedCountries $allowedCountries
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        CheckoutData $checkoutData,
        Config $config,
        AllowedCountries $allowedCountries,
        CollectionFactory $collectionFactory,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        ScopeConfigInterface $scopeConfig,
        Escaper $escaper
    ) {
        $this->checkoutData = $checkoutData;
        $this->config = $config;
        $this->allowedCountries = $allowedCountries;
        $this->collectionFactory = $collectionFactory;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->escaper = $escaper;
    }

    /**
     * @inheritdoc
     * @phpstan-return mixed[]
     */
    public function getConfig(bool $fromQuote = true): array
    {
        if (!$this->checkoutData->getPublicOrderId()) {
            $errorMsg = "No public order ID.";
            $this->logger->critical('Error in PaymentBoosterConfigProvider->getConfig(): ' . $errorMsg);
            return [];
        }

        /** @var StoreInterface&Store $store */
        $store = $this->storeManager->getStore();

        if ($fromQuote) {
            /** @var CartInterface&Quote $quote */
            $quote = $this->checkoutData->getQuote();
            /** @var StoreInterface&Store $store */
            $store = $quote->getStore();
        }

        $websiteId = (int)$store->getWebsiteId();
        $shopId = $this->config->getShopId($websiteId);
        $publicOrderId = $this->checkoutData->getPublicOrderId();
        $jwtToken = $this->checkoutData->getJwtToken();
        $epsAuthToken = $this->checkoutData->getEpsAuthToken();
        $paymentGateways = $this->checkoutData->getPaymentGateways();
        $shouldVault = $this->checkoutData->getShouldVault();
        $currency = $store->getCurrentCurrency()->getCode();
        $shopUrl = $store->getBaseUrl();
        if ($jwtToken === null || $epsAuthToken === null || $paymentGateways === []) {
            $errorMsgs = [];
            if ($jwtToken === null) {
                $errorMsgs[] = '$jwtToken is null.';
            }

            if ($epsAuthToken === null) {
                $errorMsgs[] = '$epsAuthToken is null.';
            }

            if ($paymentGateways === []) {
                $errorMsgs[] = '$paymentGateways is empty.';
            }

            $this->logger->critical('Error in PaymentBoosterConfigProvider->getConfig(): ' . implode(', ', $errorMsgs));
            return [];
        }

        $configurationGroupLabel = $this->config->getConfigurationGroupLabel($websiteId);
        if (empty($configurationGroupLabel)) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged
            $configurationGroupLabel = parse_url($shopUrl)['host'] ?? '';
        }

        return [
            'bold' => [
                'epsAuthToken' => $epsAuthToken,
                'configurationGroupLabel' => $configurationGroupLabel,
                'epsStaticUrl' => $this->config->getStaticEpsUrl($websiteId),
                'payment_gateways' => $paymentGateways,
                'vaulting_enabled' => $shouldVault,
                'jwtToken' => $jwtToken,
                'url' => $this->getBoldStorefrontUrl($websiteId, $publicOrderId),
                'shopId' => $shopId,
                'publicOrderId' => $publicOrderId,
                'countries' => $this->getAllowedCountries(),
                'origin' => rtrim($this->config->getApiUrl($websiteId), '/'),
                'epsUrl' => rtrim($this->config->getEpsUrl($websiteId), '/'),
                'shopUrl' => $shopUrl,
                'shopName' => $store->getFrontendName(),
                'isPhoneRequired' => $store->getConfig('customer/address/telephone_show')
                    === NooptreqSource::VALUE_REQUIRED,
                'isExpressPayEnabled' => $this->config->isExpressPayEnabled($websiteId),
                'isCartWalletPayEnabled' => $this->config->isCartWalletPayEnabled($websiteId),
                'isTaxIncludedInPrices' => $this->config->isTaxIncludedInPrices($websiteId),
                'isTaxIncludedInShipping' => $this->config->isTaxIncludedInShipping($websiteId),
                'paymentBooster' => [
                    'payment' => [
                        'method' => Service::CODE,
                    ],
                ],
                'currency' => $currency,
            ],
        ];
    }

    /**
     * @return mixed[]
     */
    public function getConfigWithoutQuote(): array
    {
        $result = $this->getConfig(false);
        $result['storeCode'] = $this->storeManager->getStore()->getCode();
        $result['quoteData']['entity_id'] = '';
        $result['totalsData'] = [];
        $result['checkoutAgreements']['isEnabled'] = false;
        $result['defaultSuccessPageUrl'] = $this->getDefaultSuccessPageUrl();
        $result['shippingPolicy'] = $this->getShippingPolicy();

        return $result;
    }

    /**
     * Get Bold Storefront URL.
     *
     * @param int $websiteId
     * @return string
     */
    private function getBoldStorefrontUrl(int $websiteId, string $publicOrderId): string
    {
        $apiUrl = $this->config->getApiUrl($websiteId) . 'checkout/storefront/';
        return $apiUrl . $this->config->getShopId($websiteId) . '/' . $publicOrderId . '/';
    }

    /**
     * Get allowed countries for Billing address mapping.
     *
     * @return array<array{
     *     value: string,
     *     label: string,
     *     is_region_required?: bool,
     *     is_region_visible?: bool,
     *     is_zipcode_optional?: bool
     * }>
     */
    private function getAllowedCountries(): array
    {
        if ($this->countries) {
            return $this->countries;
        }
        $allowedCountries = $this->allowedCountries->getAllowedCountries();
        $countriesCollection = $this->collectionFactory->create()->addFieldToFilter(
            'country_id',
            ['in' => $allowedCountries]
        );
        $this->countries = $countriesCollection->toOptionArray(false);

        return $this->countries;
    }

    private function getDefaultSuccessPageUrl(): string
    {
        return $this->urlBuilder->getUrl('checkout/onepage/success/');
    }

    /**
     * @return array{isEnabled: bool, shippingPolicyContent: string}
     */
    private function getShippingPolicy(): array
    {
        $policyContent = (string)$this->scopeConfig->getValue(
            'shipping/shipping_policy/shipping_policy_content',
            ScopeInterface::SCOPE_STORE
        );
        $policyContent = (string)$this->escaper->escapeHtml($policyContent);
        $result = [
            'isEnabled' => $this->scopeConfig->isSetFlag(
                'shipping/shipping_policy/enable_shipping_policy',
                ScopeInterface::SCOPE_STORE
            ),
            'shippingPolicyContent' => $policyContent ? nl2br($policyContent) : '',
        ];

        return $result;
    }
}
