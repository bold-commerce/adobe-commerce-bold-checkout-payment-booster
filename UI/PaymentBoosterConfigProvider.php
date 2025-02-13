<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\UI;

use Bold\CheckoutPaymentBooster\Model\is3rdPartyCheckout;
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
     * @var is3rdPartyCheckout
     */
    private $is3rdPartyCheckout;

    /**
     * @param CheckoutData $checkoutData
     * @param Config $config
     * @param AllowedCountries $allowedCountries
     * @param CollectionFactory $collectionFactory
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlBuilder
     * @param ScopeConfigInterface $scopeConfig
     * @param Escaper $escaper
     * @param is3rdPartyCheckout $is3rdPartyCheckout
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
        Escaper $escaper,
        is3rdPartyCheckout $is3rdPartyCheckout
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
        $this->is3rdPartyCheckout = $is3rdPartyCheckout;
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        if (!$this->checkoutData->getPublicOrderId()) {
            $errorMsg = "No public order ID.";
            $this->logger->critical('Error in PaymentBoosterConfigProvider->getConfig(): ' . $errorMsg);
            return [];
        }

        $quote = $this->checkoutData->getQuote();
        $websiteId = (int)$quote->getStore()->getWebsiteId();
        $shopId = $this->config->getShopId($websiteId);
        $publicOrderId = $this->checkoutData->getPublicOrderId();
        $jwtToken = $this->checkoutData->getJwtToken();
        $epsAuthToken = $this->checkoutData->getEpsAuthToken();
        $epsGatewayId = $this->checkoutData->getEpsGatewayId();
        $currency = $this->storeManager->getStore()->getCurrentCurrency()->getCode();
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
            $configurationGroupLabel = parse_url($quote->getStore()->getBaseUrl())['host'] ?? '';
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
                'shopUrl' => $quote->getStore()->getBaseUrl(),
                'shopName' => $quote->getStore()->getFrontendName(),
                'isPhoneRequired' => $quote->getStore()->getConfig('customer/address/telephone_show') === NooptreqSource::VALUE_REQUIRED,
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
                'thirdPartyCheckout' => $this->is3rdPartyCheckout->get3rdPartyCheckoutName()
            ],
        ];
    }

    public function getConfigWithoutQuote(): array
    {
        $result = $this->getConfig();
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
