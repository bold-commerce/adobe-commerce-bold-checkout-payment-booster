<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\UI\Payment;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Service;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
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
     * @phpstan-return array{
     *     bold?: array{
     *         paymentGatewayId: int,
     *         fastlane: array{
     *             payment: array{
     *                 method: string
     *             },
     *             styles: array{
     *                 privacy: "yes"|"no",
     *                 input: string[],
     *                 root: string[]
     *             }
     *         }
     *     }
     * }
     */
    public function getConfig(): array
    {
        /** @var CartInterface&Quote $quote */
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
