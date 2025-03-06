<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\ViewModel;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\UI\PaymentBoosterConfigProvider;
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
     * @var PaymentBoosterConfigProvider
     */
    private $paymentBoosterConfigProvider;

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
     * @param PaymentBoosterConfigProvider $paymentBoosterConfigProvider
     * @param CheckoutData $checkoutData
     * @param LoggerInterface $logger
     */
    public function __construct(
        CompositeConfigProvider $configProvider,
        PaymentBoosterConfigProvider $paymentBoosterConfigProvider,
        CheckoutData $checkoutData,
        LoggerInterface $logger
    ) {
        $this->configProvider = $configProvider;
        $this->paymentBoosterConfigProvider = $paymentBoosterConfigProvider;
        $this->checkoutData = $checkoutData;
        $this->logger = $logger;
    }

    /**
     * Initialize checkout data and return the config.
     *
     * @return array
     */
    public function getCheckoutConfig(string $pageSource): array
    {
        try {
            $this->checkoutData->initCheckoutData();

            if ($pageSource === PaymentBoosterConfigProvider::PAGE_SOURCE_PRODUCT) {
                return $this->paymentBoosterConfigProvider->getConfigWithoutQuote();
            }

            return $this->configProvider->getConfig();
        } catch (Exception $e) {
            $this->logger->error('ExpressPay: ' . $e->getMessage());
            return [];
        }
    }
}
