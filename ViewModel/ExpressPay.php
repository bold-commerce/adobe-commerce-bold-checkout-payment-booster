<?php

namespace Bold\CheckoutPaymentBooster\ViewModel;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\Country;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;
use Magento\Config\Model\Config\Source\Nooptreq as NooptreqSource;

class ExpressPay implements  ArgumentInterface
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CheckoutData
     */
    private $checkoutData;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var AllowedCountries
     */
    private $allowedCountries;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var array
     */
    protected $countries = [];

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(
        StoreManagerInterface $storeManager,
        CheckoutData $checkoutData,
        Config $config,
        AllowedCountries $allowedCountries,
        CollectionFactory $collectionFactory,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->storeManager = $storeManager;
        $this->checkoutData = $checkoutData;
        $this->config = $config;
        $this->allowedCountries = $allowedCountries;
        $this->collectionFactory = $collectionFactory;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get the current website id.
     *
     * @return int
     * @throws NoSuchEntityException
     */
    public function getWebsiteId(): int
    {
        return (int) $this->storeManager->getStore()->getWebsiteId();
    }

    /**
     * @param $websiteId
     * @return string|null
     */
    public function getEpsUrl($websiteId): ?string
    {
        return $this->config->getEpsUrl($websiteId);
    }

    /**
     * @param $websiteId
     * @return string|null
     */
    public function getStaticEpsUrl($websiteId): ?string
    {
        return $this->config->getStaticEpsUrl($websiteId);
    }

    /**
     * @param $websiteId
     * @return string|null
     */
    public function getConfigurationGroupLabel($websiteId): ?string
    {
        return $this->config->getConfigurationGroupLabel($websiteId);
    }

    /**
     * @param $websiteId
     * @return string|null
     */
    public function getApiUrl($websiteId): ?string
    {
        return $this->config->getApiUrl($websiteId);
    }

    /**
     * Get Bold Shop Id.
     *
     * @param int $websiteId
     * @return string|null
     */
    public function getShopId(int $websiteId): ?string
    {
        return $this->config->getShopId($websiteId);
    }

    /**
     *
     * @param int $websiteId
     * @return string|null
     */
    public function isExpressPayEnabled(int $websiteId): ?string
    {
        return $this->config->isExpressPayEnabled($websiteId);
    }

    /**
     * @return string|null
     */
    public function getJwtToken(): ?string
    {
        return $this->checkoutData->getJwtToken();
    }

    /**
     * @return int|null
     */
    public function getEpsGatewayId(): ?int
    {
        return $this->checkoutData->getEpsGatewayId();
    }

    /**
     * @return string|null
     */
    public function getEpsAuthToken(): ?string
    {
        return $this->checkoutData->getEpsAuthToken();
    }

    /**
     * @return string
     */
    public function getStoreUrl(): string
    {
        $quote = $this->checkoutData->getQuote();
        return $quote->getStore()->getBaseUrl();
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStoreName(): string
    {
        return $this->checkoutData->getQuote()->getStore()->getFrontendName();
    }

    /**
     * @return bool
     */
    public function getIsPhoneRequired(): bool
    {
        $quote = $this->checkoutData->getQuote();
        return $quote->getStore()->getConfig('customer/address/telephone_show') === NooptreqSource::VALUE_REQUIRED;
    }

    /**
     * @return string|null
     */
    public function getPublicOrderId(): ?string
    {
        return $this->checkoutData->getPublicOrderId();
    }

    /**
     * @param $websiteId
     * @return bool
     */
    public function isCartWalletPayEnabled($websiteId): bool
    {
        return $this->config->isCartWalletPayEnabled($websiteId);
    }

    /**
     * @param $websiteId
     * @return bool
     */
    public function isProductWalletPayEnabled($websiteId): bool
    {
        return $this->config->isProductWalletPayEnabled($websiteId);
    }

    /**
     * Get Bold Storefront URL.
     *
     * @param int $websiteId
     * @return string
     */
    public function getBoldStorefrontUrl(int $websiteId): string
    {
        $publicOrderId = $this->checkoutData->getPublicOrderId();
        $apiUrl = $this->config->getApiUrl($websiteId) . 'checkout/storefront/';
        return $apiUrl . $this->config->getShopId($websiteId) . '/' . $publicOrderId . '/';
    }

    /**
     * Get allowed countries for Billing address mapping.
     *
     * @return Country[]
     */
    public function getAllowedCountries(): array
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
}
