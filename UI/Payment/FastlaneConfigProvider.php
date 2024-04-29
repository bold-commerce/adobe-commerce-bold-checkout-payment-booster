<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\UI\Payment;

use Bold\Checkout\Api\Http\ClientInterface;
use Bold\Checkout\Model\ConfigInterface;
use Bold\CheckoutPaymentBooster\Model\Config as moduleConfig;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;

/**
 * Config provider for Bold Fastlane.
 */
class FastlaneConfigProvider implements ConfigProviderInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var moduleConfig
     */
    private $moduleConfig;

    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @param Session $checkoutSession
     * @param moduleConfig $moduleConfig
     * @param ConfigInterface $config
     * @param ClientInterface $client
     */
    public function __construct(
        Session $checkoutSession,
        moduleConfig $moduleConfig,
        ConfigInterface $config,
        ClientInterface $client
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->moduleConfig = $moduleConfig;
        $this->config = $config;
        $this->client = $client;
    }

    /**
     * @inheirtDoc
     */
    public function getConfig(): array
    {
        $boldCheckoutData = $this->checkoutSession->getBoldCheckoutData();
        $quote = $this->checkoutSession->getQuote();
        $websiteId = (int)$quote->getStore()->getWebsiteId();

        if (!$boldCheckoutData || !$this->moduleConfig->isFastlaneEnabled($websiteId)) {
            return [];
        }

        $shopId = $this->config->getShopId($websiteId);
        $publicOrderId = $boldCheckoutData['data']['public_order_id'] ?? null;
        $jwtToken = $boldCheckoutData['data']['jwt_token'] ?? null;

        if (!$websiteId || !$shopId || !$publicOrderId || !$jwtToken) {
            return [];
        }

        return [
            'bold_fastlane' => [
                'enabled' => $this->moduleConfig->isFastlaneEnabled($websiteId),
                'jwtToken' => $jwtToken,
                'url' => $this->client->getUrl($websiteId, ''),
                'shopId' => $shopId,
                'publicOrderId' => $publicOrderId,
                'payment' => [
                    'method' => 'bold_fastlane',
                ],
            ],
        ];
    }
}
