<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\ViewModel;

use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Checkout\Model\Session;
use Magento\Store\Model\StoreManagerInterface;
use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;

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
     * @var Config
     */
    private $config;

    /**
     * @var mixed[]
     */
    private $jsLayout = [];

    public function __construct(
        CompositeConfigProvider $configProvider,
        SerializerInterface $serializer,
        Session $checkoutSession,
        StoreManagerInterface $storeManager,
        Config $config
    ) {
        $this->configProvider = $configProvider;
        $this->serializer = $serializer;
        $this->checkoutSession = $checkoutSession;
        $this->storeManager = $storeManager;
        $this->config = $config;
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
        $websiteId = (int) $this->storeManager->getStore()->getWebsiteId();
        return $this->config->isCartWalletPayEnabled($websiteId);
    }

    /**
     * @param int $websiteId
     * @return bool
     */
    public function isProductWalletPayEnabled(int $websiteId): bool
    {
        return $this->config->isProductWalletPayEnabled($websiteId);
    }

    /**
     * @return bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function hasActiveQuote(): bool
    {
        $quote = $this->checkoutSession->getQuote();
        return $quote->getId() !== null;
    }
}
