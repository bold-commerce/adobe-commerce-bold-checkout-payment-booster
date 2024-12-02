<?php

namespace Bold\CheckoutPaymentBooster\ViewModel;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Checkout\Model\Session;
use Magento\Store\Model\StoreManagerInterface;
use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\Country;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;
use Magento\Config\Model\Config\Source\Nooptreq as NooptreqSource;
use Bold\CheckoutPaymentBooster\UI\PaymentBoosterConfigProvider;

class ExpressPay implements ArgumentInterface
{
    /**
     * @var CompositeConfigProvider
     */
    protected $configProvider;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

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
     * @var array
     */
    protected $jsLayout = [];

    /**
     * @var PaymentBoosterConfigProvider
     */
    protected $paymentBoosterConfigProvider;

    public function __construct(
        CompositeConfigProvider $configProvider,
        SerializerInterface $serializer,
        Session $checkoutSession,
        ManagerInterface $eventManager,
        StoreManagerInterface $storeManager,
        CheckoutData $checkoutData,
        Config $config,
        AllowedCountries $allowedCountries,
        CollectionFactory $collectionFactory,
        PaymentBoosterConfigProvider $paymentBoosterConfigProvider,
    ) {
        $this->configProvider = $configProvider;
        $this->serializer = $serializer;
        $this->checkoutSession = $checkoutSession;
        $this->eventManager = $eventManager;
        $this->storeManager = $storeManager;
        $this->checkoutData = $checkoutData;
        $this->config = $config;
        $this->allowedCountries = $allowedCountries;
        $this->collectionFactory = $collectionFactory;
        $this->paymentBoosterConfigProvider = $paymentBoosterConfigProvider;
    }

    public function getJsLayout()
    {
        $this->jsLayout['checkoutConfig'] = $this->configProvider->getConfig();
        return $this->serializer->serialize($this->jsLayout);
    }

    /**
     * @return void
     */
    public function initializeCheckoutData()
    {
        $checkoutData = $this->checkoutSession->getBoldCheckoutData();
        if (!$checkoutData || !$checkoutData['data']['public_order_id']) {
            $this->eventManager->dispatch('bold_checkout_data_action');
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

    public function initConfig(): array
    {   
        //TODO: For pages other than cart
            //initialize checkout config
            //initialize quote
        $this->checkoutData->initCheckoutData();
        $config = $this->paymentBoosterConfigProvider->getConfig();

        return $config;
    }
}
