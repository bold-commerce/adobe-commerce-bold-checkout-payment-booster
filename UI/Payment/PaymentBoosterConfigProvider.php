<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\UI\Payment;

use Bold\Checkout\Api\Http\ClientInterface;
use Bold\Checkout\Model\Payment\Gateway\Service;
use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\ValidatorException;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Config provider for Payment Booster.
 */
class PaymentBoosterConfigProvider implements ConfigProviderInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param Session $checkoutSession
     * @param ClientInterface $client
     * @param Config $config
     */
    public function __construct(
        Session $checkoutSession,
        ClientInterface $client,
        Config $config
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function getConfig(): array
    {
        $boldCheckoutData = $this->checkoutSession->getBoldCheckoutData();
        $quote = $this->checkoutSession->getQuote();
        $websiteId = (int)$quote->getStore()->getWebsiteId();

        if (!$boldCheckoutData
            || !$this->config->isPaymentBoosterEnabled($websiteId)
        ) {
            return [];
        }

        $publicOrderId = $boldCheckoutData['data']['public_order_id'] ?? null;
        $jwtToken = $boldCheckoutData['data']['jwt_token'] ?? null;

        return [
            'bold' => [
                'payment_booster' => [
                    'payment' => [
                        'iframeSrc' => $this->getIframeSrc($publicOrderId, $jwtToken, $websiteId),
                        'method' => Service::CODE,
                    ],
                ],
            ],
        ];
    }

    /**
     * Get iframe src.
     *
     * @param string|null $publicOrderId
     * @param string|null $jwtToken
     * @param int $websiteId
     * @return string|null
     */
    private function getIframeSrc(
        ?string $publicOrderId,
        ?string $jwtToken,
        int $websiteId
    ): ?string {
        if (!$publicOrderId || !$jwtToken) {
            return null;
        }

        return $this->client->getUrl($websiteId, 'payments/iframe?token=' . $jwtToken);
    }
}
