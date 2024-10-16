<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\UI\Payment;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Service;
use Magento\Checkout\Model\ConfigProviderInterface;

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
     * @param CheckoutData $checkoutData
     * @param Config $config
     */
    public function __construct(
        CheckoutData $checkoutData,
        Config $config
    ) {
        $this->checkoutData = $checkoutData;
        $this->config = $config;
    }

    /**
     * @inheirtDoc
     */
    public function getConfig(): array
    {
        $quote = $this->checkoutData->getQuote();
        $websiteId = (int)$quote->getStore()->getWebsiteId();
        if (!$this->checkoutData->getPublicOrderId()
            || !$this->config->isFastlaneEnabled($websiteId) || $quote->getCustomer()->getId()) {
            return [];
        }
        return [
            'bold' => [
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
