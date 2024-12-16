<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\ViewModel;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Bold\CheckoutPaymentBooster\UI\PaymentBoosterConfigProvider;
use Magento\Framework\App\RequestInterface;

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
     * @var mixed[]
     */
    private $jsLayout = [];

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
        $quoteId = $this->checkoutSession->getQuote()->getId();
        if($quoteId !== null) {
           $this->jsLayout['checkoutConfig'] = $this->configProvider->getConfig();
        }
        else {
           $this->checkoutData->initCheckoutData();
           $this->jsLayout['checkoutConfig'] = $this->paymentBoosterConfigProvider->getConfig();
           $this->jsLayout['checkoutConfig']['storeCode'] = $this->storeManager->getStore()->getCode();
           $this->jsLayout['checkoutConfig']['totalsData'] = [];
           $this->jsLayout['checkoutConfig']['quoteData']['entity_id'] = '';
        }

        return $this->serializer->serialize($this->jsLayout);
    }

    /**
     * @return bool
     */
    private function isCartWalletPayEnabled(): bool
    {
        $websiteId = (int)$this->storeManager->getStore()->getWebsiteId();
        return $this->config->isCartWalletPayEnabled($websiteId);
    }

    /**
     * @return bool
     */
    private function isProductWalletPayEnabled(): bool
    {
        $websiteId = (int)$this->storeManager->getStore()->getWebsiteId();
        return $this->config->isProductWalletPayEnabled($websiteId);
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    private function hasActiveQuote(): bool
    {
        $quote = $this->checkoutSession->getQuote();
        return $quote->getId() !== null;
    }

    /**
     * @return bool
     */
    public function isEnabled($pageSource = ''): bool {
        $isEnabled = false;
        $hasActiveQuote = $this->hasActiveQuote();

        switch ($pageSource) {
            case PaymentBoosterConfigProvider::PAGE_SOURCE_CART:
                $isEnabled = $this->isCartWalletPayEnabled() && $hasActiveQuote;
                break;            
            case PaymentBoosterConfigProvider::PAGE_SOURCE_PRODUCT:
                $isEnabled = $this->isProductWalletPayEnabled();
                break;            
            case PaymentBoosterConfigProvider::PAGE_SOURCE_MINICART:
                $isEnabled = $this->isCartWalletPayEnabled() && $hasActiveQuote;
                break;
        }

        return $isEnabled;
    }
}
