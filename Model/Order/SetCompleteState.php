<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\Checkout\Api\Http\ClientInterface;
use Exception;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

/**
 * Mark order as completed.
 */
class SetCompleteState
{
    private const COMPLETE_URL = 'checkout/orders/{{shopId}}/%s/complete';

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param ClientInterface $client
     * @param LoggerInterface $logger
     */
    public function __construct(
        ClientInterface $client,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Mark order as completed.
     *
     * @param OrderInterface $order
     * @param string $publicOrderId
     * @return void
     * @throws Exception
     */
    public function execute(OrderInterface $order, string $publicOrderId): void
    {
        $websiteId = (int)$order->getStore()->getWebsiteId();
        $url = sprintf(self::COMPLETE_URL, $publicOrderId);
        $params = [
            'platform_order_id' => $order->getEntityId(),
            'platform_friendly_id' => $order->getIncrementId(),
        ];
        $response = $this->client->post($websiteId, $url, $params);
        if ($response->getStatus() !== 200) {
            $this->logger->error(__('Failed to set complete state for order with id="%1"', $order->getEntityId()));
        }
    }
}
