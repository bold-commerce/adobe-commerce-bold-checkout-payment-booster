<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\UI\Payment;

use Bold\Checkout\Api\Http\ClientInterface;
use Bold\CheckoutPaymentBooster\Model\Config;
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
    private const PAYPAL_FASTLANE_CLIENT_TOKEN_URL = 'checkout/orders/{shopId}/%s/paypal_fastlane/client_token';

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var StoreManagerInterface
     */
    private $storeManagement;

    /**
     * @param Session $checkoutSession
     * @param Config $config
     * @param ClientInterface $client
     * @param StoreManagerInterface $storeManagement
     */
    public function __construct(
        Session $checkoutSession,
        Config $config,
        ClientInterface $client,
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
            if (!$boldCheckoutData || !$this->config->isFastlaneEnabled($websiteId)) {
                return [];
            }
            $publicOrderId = $boldCheckoutData['data']['public_order_id'] ?? null;
            if (!$publicOrderId) {
                return [];
            }
            $gatewayData = $this->getGatewayData($websiteId, $publicOrderId);
        } catch (Exception $e) {
            return [];
        }
        return [
            'bold' => [
                'fastlane' => [
                    'gatewayData' => $gatewayData,
                    'payment' => [
                        'method' => 'bold_fastlane',
                    ],
                    'styles' => [], // Add custom styles for the Fastlane Payment Component here if needed.
                ],
            ],
        ];
    }

    /**
     * Retrieve gateway data.
     *
     * @param int $websiteId
     * @param string $publicOrderId
     * @return array
     * @throws Exception
     */
    private function getGatewayData(int $websiteId, string $publicOrderId): array
    {
        $apiUrl = sprintf(self::PAYPAL_FASTLANE_CLIENT_TOKEN_URL, $publicOrderId);
        $baseUrl = $this->storeManagement->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);
        $domain = preg_replace('#^https?://|/$#', '', $baseUrl);
        $response = $this->client->post(
            $websiteId,
            $apiUrl,
            [
                "domains" => [
                    $domain,
                ],
            ]
        );
        if ($response->getErrors()) {
            throw new Exception('Something went wrong while fetching the Fastlane gateway data.');
        }
        return $response->getBody()['data'];
    }
}
