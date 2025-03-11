<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\UI;

use Magento\Directory\Model\AllowedCountries;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Directory\Model\Country;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;
use Magento\Framework\Escaper;
use Psr\Log\LoggerInterface;
use Magento\Config\Model\Config\Source\Nooptreq as NooptreqSource;
use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Service;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;


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
     * @var array
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
     */
    public function getConfig(bool $fromQuote = true): array
    {
        if (!$this->checkoutData->getPublicOrderId()) {
            $errorMsg = "No public order ID.";
            $this->logger->critical('Error in PaymentBoosterConfigProvider->getConfig(): ' . $errorMsg);
            return [];
        }

        $store = $this->storeManager->getStore();

        if ($fromQuote) {
            $quote = $this->checkoutData->getQuote();
            $store = $quote->getStore();
        }

        $websiteId = (int)$store->getWebsiteId();
        $shopId = $this->config->getShopId($websiteId);
        $publicOrderId = $this->checkoutData->getPublicOrderId();
        $jwtToken = $this->checkoutData->getJwtToken();
        $epsAuthToken = $this->checkoutData->getEpsAuthToken();
        $epsGatewayId = $this->checkoutData->getEpsGatewayId();
        $currency = $store->getCurrentCurrency()->getCode();
        $shopUrl = $store->getBaseUrl();

        if ($jwtToken === null || $epsAuthToken === null || $epsGatewayId === null) {
            $errorMsgs = [];
            if ($jwtToken === null) {
                $errorMsgs[] = '$jwtToken is null.';
            }

            if ($epsAuthToken === null) {
                $errorMsgs[] = '$epsAuthToken is null.';
            }

            if ($epsGatewayId === null) {
                $errorMsgs[] = '$epsGatewayId is null.';
            }

            $this->logger->critical('Error in PaymentBoosterConfigProvider->getConfig(): ' . implode(', ', $errorMsgs));
            return [];
        }

        $configurationGroupLabel = $this->config->getConfigurationGroupLabel($websiteId);
        if (empty($configurationGroupLabel)) {
            $configurationGroupLabel = parse_url($shopUrl)['host'] ?? '';
        }

        return [
            'bold' => [
                'epsAuthToken' => $epsAuthToken,
                'configurationGroupLabel' => $configurationGroupLabel,
                'epsUrl' => $this->config->getEpsUrl($websiteId),
                'epsStaticUrl' => $this->config->getStaticEpsUrl($websiteId),
                'gatewayId' => $epsGatewayId,
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
                'currency' => $currency
            ],
        ];
    }

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
     * @return Country[]
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

    private function getDefaultSuccessPageUrl()
    {
        return $this->urlBuilder->getUrl('checkout/onepage/success/');
    }

    private function getShippingPolicy()
    {
        $policyContent = $this->scopeConfig->getValue(
            'shipping/shipping_policy/shipping_policy_content',
            ScopeInterface::SCOPE_STORE
        );
        $policyContent = $this->escaper->escapeHtml($policyContent);
        $result = [
            'isEnabled' => $this->scopeConfig->isSetFlag(
                'shipping/shipping_policy/enable_shipping_policy',
                ScopeInterface::SCOPE_STORE
            ),
            'shippingPolicyContent' => $policyContent ? nl2br($policyContent) : ''
        ];

        return $result;
    }
}
