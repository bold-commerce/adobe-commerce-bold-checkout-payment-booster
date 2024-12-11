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
use Magento\Framework\App\RequestInterface;
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
     * @var RequestInterface
     */
    private $request;

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
        PaymentBoosterConfigProvider $paymentBoosterConfigProvider,
        RequestInterface $request
    ) {
        $this->configProvider = $configProvider;
        $this->serializer = $serializer;
        $this->checkoutSession = $checkoutSession;
        $this->storeManager = $storeManager;
        $this->checkoutData = $checkoutData;
        $this->config = $config;
        $this->paymentBoosterConfigProvider = $paymentBoosterConfigProvider;
        $this->request = $request;
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
     */
    private function isCartWalletPayEnabled(): bool
    {
        $websiteId = $this->storeManager->getStore()->getWebsiteId();
        return $this->config->isCartWalletPayEnabled($websiteId);
    }

    /**
     * @return bool
     */
    private function isProductWalletPayEnabled(): bool
    {
        $websiteId = $this->storeManager->getStore()->getWebsiteId();
        return $this->config->isProductWalletPayEnabled($websiteId);
    }

    /**
     * @return bool
     */
    private function hasActiveQuote(): bool
    {
        $quote = $this->checkoutSession->getQuote();
        return $quote->getId() !== null;
    }

    /**
     * @return string
     */
    private function getFullActionName(): string {
        $moduleName = $this->request->getModuleName();
        $controllerName = $this->request->getControllerName();
        $actionName = $this->request->getActionName();

        $fullActionName = $moduleName . '_' . $controllerName . '_' . $actionName;

        return $fullActionName;
    }

    /**
     * @return bool
     */
    public function isEnabled($pageSource = ''): bool {
        $isEnabled = false;
        $hasActiveQuote = $this->hasActiveQuote();
        $isMinicartRendered = $this->getFullActionName() !== 'checkout_cart_index'
                    && $this->getFullActionName() !== 'catalog_product_view';

        switch ($pageSource) {
            case PaymentBoosterConfigProvider::PAGE_SOURCE_CART:
                $isEnabled = $this->isCartWalletPayEnabled();
                break;            
            case PaymentBoosterConfigProvider::PAGE_SOURCE_PRODUCT:
                $isEnabled = $this->isProductWalletPayEnabled();
                break;            
            case PaymentBoosterConfigProvider::PAGE_SOURCE_MINICART:
                $isEnabled = $this->isCartWalletPayEnabled() && $isMinicartRendered;
                break;
        }

        return $isEnabled && $hasActiveQuote;
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function initConfig(): array
    {
        $this->checkoutData->initCheckoutData();
        return $this->paymentBoosterConfigProvider->getConfig();
    }
}
