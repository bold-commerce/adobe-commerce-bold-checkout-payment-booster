<?php

declare(strict_types=1);

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
    private $jsLayout = [];

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
    public function isCartWalletPayEnabled(): bool
    {
        $websiteId = $this->storeManager->getStore()->getWebsiteId();
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
     * @return bool
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function hasActiveQuote(): bool
    {
        $quote = $this->checkoutSession->getQuote();
        return $quote->getId() !== null;
    }

    /**
     * @return array{
     *     bold: array{
     *         epsAuthToken: string,
     *         configurationGroupLabel: string,
     *         epsUrl: string,
     *         epsStaticUrl: string,
     *         gatewayId: int,
     *         jwtToken: string,
     *         url: string,
     *         shopId: string,
     *         publicOrderId: string,
     *         countries: array{
     *             is_region_visible: bool,
     *             label: string,
     *             value: string
     *         },
     *         origin: string,
     *         epsUrl: string,
     *         shopUrl: string,
     *         shopName: string,
     *         isPhoneRequired: bool,
     *         isExpressPayEnabled: bool,
     *         isCartWalletPayEnabled: bool,
     *         paymentBooster: array{
     *             payment: object{
     *                 method: string
     *             }
     *         }
     *     }
     * }
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function initConfig(): array
    {
        $this->checkoutData->initCheckoutData();
        return $this->paymentBoosterConfigProvider->getConfig();
    }
}
