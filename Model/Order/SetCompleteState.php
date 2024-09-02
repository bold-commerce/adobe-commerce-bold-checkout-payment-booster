<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

/**
 * Mark order as completed.
 */
class SetCompleteState
{
    private const STATE_URL = 'checkout_sidekick/{{shopId}}/order/%s/state';

    /**
     * @var BoldClient
     */
    private $client;

    /**
     * @var GetOrderPublicIdByOrderId
     */
    private $getOrderPublicId;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param BoldClient $client
     * @param GetOrderPublicIdByOrderId $getOrderPublicId
     * @param LoggerInterface $logger
     */
    public function __construct(
        BoldClient                $client,
        GetOrderPublicIdByOrderId $getOrderPublicId,
        LoggerInterface           $logger
    ) {
        $this->client = $client;
        $this->getOrderPublicId = $getOrderPublicId;
        $this->logger = $logger;
    }

    /**
     * Mark order as completed.
     *
     * @param OrderInterface $order
     * @return void
     * @throws LocalizedException
     */
    public function execute(OrderInterface $order): void
    {
        $websiteId = (int)$order->getStore()->getWebsiteId();
        $publicOrderId = $this->getOrderPublicId->execute((int)$order->getEntityId());
        $url = sprintf(self::STATE_URL, $publicOrderId);
        $params = [
            'state' => 'order_complete',
            'platform_order_id' => $order->getIncrementId(),
            'platform_friendly_id' => $order->getEntityId()
        ];
        $response = $this->client->put($websiteId, $url, $params);
        if ($response->getStatus() !== 201) {
            $this->logger->error(__('Failed to set complete state for order with id="%1"', $order->getEntityId()));
        }
    }
}
