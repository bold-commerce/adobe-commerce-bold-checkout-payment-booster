<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\Config;

use Bold\CheckoutPaymentBooster\Api\Config\GetCheckoutConfigInterface;
use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Hydrate Bold order from Magento quote.
 */
class GetCheckoutConfig implements GetCheckoutConfigInterface
{
    /**
     * @var CompositeConfigProvider
     */
    private $configProvider;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(
        CompositeConfigProvider $configProvider,
        SerializerInterface $serializer
    ) {
        $this->configProvider = $configProvider;
        $this->serializer = $serializer;
    }

    /**
     * Gets Current Checkout Configuration.
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCheckoutConfig() {
        $jsLayout = [];
        $jsLayout['checkoutConfig'] = $this->configProvider->getConfig();

        $config = $this->serializer->serialize($jsLayout);

        return $config;
    }
}
