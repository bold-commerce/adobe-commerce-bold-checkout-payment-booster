<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\UI\Payment;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Service;
use Magento\Checkout\Model\ConfigProviderInterface;
use Psr\Log\LoggerInterface;

use function __;

/**
 * Config provider for Bold Fastlane.
 */
class FastlaneConfigProvider implements ConfigProviderInterface
{
    /**
     * @var CheckoutData
     */
    private $checkoutData;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param CheckoutData $checkoutData
     * @param Config $config
     */
    public function __construct(
        CheckoutData $checkoutData,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->checkoutData = $checkoutData;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @inheirtDoc
     */
    public function getConfig(): array
    {
        $quote = $this->checkoutData->getQuote();
        $websiteId = (int)$quote->getStore()->getWebsiteId();
        if (
            !$this->checkoutData->getPublicOrderId()
            || !$this->config->isFastlaneEnabled($websiteId) || $quote->getCustomer()->getId()
        ) {
            return [];
        }

        $paymentGatewayId = $this->checkoutData->getPaymentGatewayId();
        if (!$paymentGatewayId) {
            $this->logger->critical(__('Could not get payment gateway ID.'));
            return [];
        }

        return [
            'bold' => [
                'paymentGatewayId' => $paymentGatewayId,
                'fastlane' => [
                    'payment' => [
                        'method' => Service::CODE_FASTLANE,
                    ],
                    'styles' => $this->checkoutData->getFastlaneStyles(),
                ],
            ],
        ];
    }
}
