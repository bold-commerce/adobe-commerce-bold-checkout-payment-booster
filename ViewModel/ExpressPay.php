<?php

namespace Bold\CheckoutPaymentBooster\ViewModel;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Checkout\Model\Session;
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
     * @var Session
     */
    protected $checkoutSession;
    /**
     * @var ManagerInterface
     */
    protected $eventManager;
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
        Session $checkoutSession,
        ManagerInterface $eventManager,
        StoreManagerInterface $storeManager,
        CheckoutData $checkoutData,
        Config $config,
        AllowedCountries $allowedCountries,
        CollectionFactory $collectionFactory,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->eventManager = $eventManager;
        $this->storeManager = $storeManager;
        $this->checkoutData = $checkoutData;
        $this->config = $config;
        $this->allowedCountries = $allowedCountries;
        $this->collectionFactory = $collectionFactory;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return void
     */
    public function initializeCheckoutData()
    {
        $checkoutData = $this->checkoutSession->getBoldCheckoutData();
        echo '<pre>', var_dump(['SESSION TEST1', $this->checkoutSession->getTestData()]), '</pre>';
        $this->checkoutSession->setTestData('MY DATA');
        echo '<pre>', var_dump(['SESSION TEST2', $this->checkoutSession->getTestData()]), '</pre>';
//        var_dump(['CHECKOUT DATA', $checkoutData]);
//        $publicOrderId = $checkoutData['data']['public_order_id'];

        if (!$checkoutData || !$checkoutData['data']['public_order_id']) {
            $this->eventManager->dispatch('bold_checkout_data_action');
        }
        $checkoutData = $this->checkoutSession->getBoldCheckoutData();
        if ($checkoutData) {
            echo '<pre>', var_dump(['ORDER ID', $checkoutData['data']['public_order_id']]), '</pre>';
        }
    }

    /**
     * Get the current website id.
     *
     * @return int
     * @throws NoSuchEntityException
     */
    public function getWebsiteId()
    {
        return (int) $this->storeManager->getStore()->getWebsiteId();
    }

    /**
     * @param $websiteId
     * @return string|null
     */
    public function getEpsUrl($websiteId)
    {
        return $this->config->getEpsUrl($websiteId);
    }

    /**
     * @param $websiteId
     * @return string|null
     */
    public function getStaticEpsUrl($websiteId)
    {
        return $this->config->getStaticEpsUrl($websiteId);
    }

    /**
     * @param $websiteId
     * @return string|null
     */
    public function getConfigurationGroupLabel($websiteId)
    {
        return $this->config->getConfigurationGroupLabel($websiteId);
    }

    /**
     * @param $websiteId
     * @return string|null
     */
    public function getApiUrl($websiteId)
    {
        return $this->config->getApiUrl($websiteId);
    }

    /**
     * Get Bold Shop Id.
     *
     * @param int $websiteId
     * @return string|null
     */
    public function getShopId(int $websiteId)
    {
        return $this->config->getShopId($websiteId);
    }

    /**
     *
     * @param int $websiteId
     * @return string|null
     */
    public function isExpressPayEnabled(int $websiteId)
    {
        return $this->config->isExpressPayEnabled($websiteId);
    }

    /**
     * @return string|null
     */
    public function getJwtToken()
    {
        return $this->checkoutData->getJwtToken();
    }

    /**
     * @return int|null
     */
    public function getEpsGatewayId()
    {
        return $this->checkoutData->getEpsGatewayId();
    }

    /**
     * @return string|null
     */
    public function getEpsAuthToken()
    {
        return $this->checkoutData->getEpsAuthToken();
    }

    /**
     * @return string
     */
    public function getStoreUrl()
    {
        $quote = $this->checkoutData->getQuote();
        return $quote->getStore()->getBaseUrl();
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStoreName()
    {
        return $this->checkoutData->getQuote()->getStore()->getFrontendName();
    }

    /**
     * @param int $websiteId
     * @return string|null
     * @throws NoSuchEntityException
     */
    public function getStoreCurrency(int $websiteId)
    {
        return $this->storeManager->getStore($websiteId)->getCurrentCurrencyCode();
    }

    /**
     * @return bool
     */
    public function getIsPhoneRequired()
    {
        $quote = $this->checkoutData->getQuote();
        return $quote->getStore()->getConfig('customer/address/telephone_show') === NooptreqSource::VALUE_REQUIRED;
    }

    /**
     * @return string|null
     */
    public function getPublicOrderId()
    {
        return $this->checkoutData->getPublicOrderId();
    }

    /**
     * @param $websiteId
     * @return bool
     */
    public function isCartWalletPayEnabled($websiteId)
    {
        return $this->config->isCartWalletPayEnabled($websiteId);
    }

    /**
     * @param $websiteId
     * @return bool
     */
    public function isProductWalletPayEnabled($websiteId)
    {
        return $this->config->isProductWalletPayEnabled($websiteId);
    }

    /**
     * Get Bold Storefront URL.
     *
     * @param int $websiteId
     * @return string
     */
    public function getBoldStorefrontUrl(int $websiteId)
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
    public function getAllowedCountries()
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
