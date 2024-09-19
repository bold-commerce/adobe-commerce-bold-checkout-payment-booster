<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\UI\Payment;

use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Exception;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Config provider for Bold Fastlane.
 */
class FastlaneConfigProvider implements ConfigProviderInterface
{
    private const PAYPAL_FASTLANE_CLIENT_TOKEN_URL = 'checkout/orders/{{shopId}}/%s/paypal_fastlane/client_token';

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var BoldClient
     */
    private $client;

    /**
     * @var StoreManagerInterface
     */
    private $storeManagement;

    /**
     * @param Session $checkoutSession
     * @param Config $config
     * @param BoldClient $client
     * @param StoreManagerInterface $storeManagement
     */
    public function __construct(
        Session $checkoutSession,
        Config $config,
        BoldClient $client,
        StoreManagerInterface $storeManagement
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->config = $config;
        $this->client = $client;
        $this->storeManagement = $storeManagement;
    }

    /**
     * @inheirtDoc
     */
    public function getConfig(): array
    {
        try {
            $boldCheckoutData = $this->checkoutSession->getBoldCheckoutData();
            $quote = $this->checkoutSession->getQuote();
            $websiteId = (int)$quote->getStore()->getWebsiteId();
            if (!$boldCheckoutData
                || !$this->config->isPaymentBoosterEnabled($websiteId)
                || !$this->config->isFastlaneEnabled($websiteId) || $quote->getCustomer()->getId()) {
                return [];
            }
            $publicOrderId = $boldCheckoutData['data']['public_order_id'] ?? null;
            if (!$publicOrderId) {
                return [];
            }
            $styles = $boldCheckoutData['data']['initial_data']['alternative_payment_methods'][0]['fastlane_styles']
                ?? [];
        } catch (Exception $e) {
            return [];
        }
        return [
            'bold' => [
                'fastlane' => [
                    'payment' => [
                        'method' => 'bold_fastlane',
                    ],
                    'styles' => $styles,
                ],
            ],
        ];
    }
}
