<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\ViewModel;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Exception;
use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Psr\Log\LoggerInterface;

class ExpressPay implements ArgumentInterface
{
    /**
     * @var CompositeConfigProvider
     */
    private $configProvider;

    /**
     * @var CheckoutData
     */
    private $checkoutData;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param CompositeConfigProvider $configProvider
     * @param CheckoutData $checkoutData
     * @param LoggerInterface $logger
     */
    public function __construct(
        CompositeConfigProvider $configProvider,
        CheckoutData $checkoutData,
        LoggerInterface $logger
    ) {
        $this->configProvider = $configProvider;
        $this->checkoutData = $checkoutData;
        $this->logger = $logger;
    }

    /**
     * Initialize checkout data and return the config.
     *
     * @return array
     */
    public function getCheckoutConfig(): array
    {
        try {
            $this->checkoutData->initCheckoutData();
            return $this->configProvider->getConfig();
        } catch (Exception $e) {
            $this->logger->error('ExpressPay: ' . $e->getMessage());
            return [];
        }
    }
}
