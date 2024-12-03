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
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isCartWalletPayEnabled()
    {
        $websiteId = $this->storeManager->getStore()->getWebsiteId();
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

    public function initConfig(): array
    {
        
        $this->checkoutData->initCheckoutData();
        return $this->paymentBoosterConfigProvider->getConfig();
    }
}
