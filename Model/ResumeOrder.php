<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Exception;

/**
 * Resume Bold order.
 */
class ResumeOrder
{
    private const RESUME_SIMPLE_ORDER_URI = '/checkout_sidekick/{{shopId}}/order/{{publicOrderId}}/resume';

    /**
     * @var BoldClient
     */
    private $client;

    /**
     * @param BoldClient $client
     */
    public function __construct(BoldClient $client)
    {
        $this->client = $client;
    }

    /**
     * Resume order by public order id.
     *
     * @param string $publicOrderId
     * @param int $websiteId
     * @return array
     */
    public function resume(string $publicOrderId, int $websiteId): array
    {
        $path = str_replace('{{publicOrderId}}', $publicOrderId, self::RESUME_SIMPLE_ORDER_URI);
        $orderData = $this->client->post(
            $websiteId,
            $path,
            []
        );
        if ($orderData->getErrors() || !isset($orderData->getBody()['data']['public_order_id'])) {
            return [];
        }
        return isset($orderData->getBody()['data']['jwt_token']) ? $orderData->getBody() : [];
    }
}
