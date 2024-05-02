<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\UI\Payment;

use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;

/**
 * Config provider for Bold Fastlane.
 */
class PaypalInsightsConfigProvider implements ConfigProviderInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Session $checkoutSession
     * @param Config $config
     */
    public function __construct(
        Session $checkoutSession,
        Config $config
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->config = $config;
    }

    /**
     * @inheirtDoc
     */
    public function getConfig(): array
    {
        return [
            'bold' => [
                'paypal_insights' => [
                    'enabled' => $this->config->isPayPalInsightsEnabled(
                        (int)$this->checkoutSession->getQuote()->getStore()->getWebsiteId()
                    ),
                ],
            ],
        ];
    }
}
