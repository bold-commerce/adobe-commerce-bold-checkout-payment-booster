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
     * @return array{
     *     data?: array{
     *         flow_settings: array{
     *             eps_auth_token: string,
     *             eps_gateway_id: string,
     *             eps_gateway_type: string,
     *             flow_id: string,
     *             flow_type: string,
     *             is_test_mode: bool,
     *             fastlane_styles: array{
     *                 privacy: "yes"|"no",
     *                 input: string[],
     *                 root: string[]
     *             }
     *         },
     *         jwt_token: string,
     *         payment_gateways: array{
     *             auth_token: string,
     *             currency: string,
     *             gateway: string,
     *             id: int,
     *             is_test_mode: bool
     *         }[],
     *         public_order_id: string
     *     }
     * }
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
