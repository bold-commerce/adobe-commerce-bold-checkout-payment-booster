<?php

namespace Bold\CheckoutPaymentBooster\ViewModel;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Checkout\Model\Session;
use Magento\Store\Model\StoreManagerInterface;
use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;
use Bold\CheckoutPaymentBooster\UI\PaymentBoosterConfigProvider;

class ExpressPay implements ArgumentInterface
{
    /**
     * @var CompositeConfigProvider
     */
    private $configProvider;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var CheckoutData
     */
    private $checkoutData;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var PaymentBoosterConfigProvider
     */
    private $paymentBoosterConfigProvider;

    /**
     * @var array
     */
    protected $jsLayout = [];

    public function __construct(
        CompositeConfigProvider $configProvider,
        SerializerInterface $serializer,
        Session $checkoutSession,
        StoreManagerInterface $storeManager,
        CheckoutData $checkoutData,
        Config $config,
        PaymentBoosterConfigProvider $paymentBoosterConfigProvider
    ) {
        $this->configProvider = $configProvider;
        $this->serializer = $serializer;
        $this->checkoutSession = $checkoutSession;
        $this->storeManager = $storeManager;
        $this->checkoutData = $checkoutData;
        $this->config = $config;
        $this->paymentBoosterConfigProvider = $paymentBoosterConfigProvider;
    }

    /**
     * @return bool|string
     */
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

    /**
     * @return bool
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function hasActiveQuote()
    {
        $quote = $this->checkoutSession->getQuote();
        return $quote->getId() !== null;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function initConfig()
    {
        $this->checkoutData->initCheckoutData();
        return $this->paymentBoosterConfigProvider->getConfig();
    }
}
