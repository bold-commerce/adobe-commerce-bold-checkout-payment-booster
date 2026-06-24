<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Payment;

use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Bold\CheckoutPaymentBooster\Model\Log\CheckoutOrderTracer;
use Magento\Framework\Exception\LocalizedException;

/**
 * Fully authorize payments.
 */
class Authorize
{
    private const PATH_PAYMENTS_AUTH = 'checkout/orders/{{shopId}}/%s/payments/auth/full';

    /**
     * @var BoldClient
     */
    private $client;

    /**
     * @var CheckoutOrderTracer
     */
    private $checkoutOrderTracer;

    /**
     * @param BoldClient $client
     * @param CheckoutOrderTracer $checkoutOrderTracer
     */
    public function __construct(
        BoldClient $client,
        CheckoutOrderTracer $checkoutOrderTracer
    ) {
        $this->client = $client;
        $this->checkoutOrderTracer = $checkoutOrderTracer;
    }

    /**
     * Fully authorize payments.
     *
     * @param string $publicOrderId
     * @param int $websiteId
     * @param int|null $quoteId
     * @return array{
     *     data: array{
     *         transactions: array{
     *             transaction_id: string,
     *             tender_details: array{
     *                 account: string,
     *                 email: string
     *             }
     *         }[]
     *     }
     * }
     * @throws LocalizedException
     */
    public function execute(string $publicOrderId, int $websiteId, ?int $quoteId = null): array
    {
        $url = sprintf(self::PATH_PAYMENTS_AUTH, $publicOrderId);
        $this->checkoutOrderTracer->trace('auth_full_start', [
            'public_order_id' => $publicOrderId,
            'website_id' => $websiteId,
            'quote_id' => $quoteId,
            'url' => $url,
        ]);
        $result = $this->client->post($websiteId, $url, []);
        if ($result->getErrors()) {
            $this->checkoutOrderTracer->trace('auth_full_failed', [
                'public_order_id' => $publicOrderId,
                'website_id' => $websiteId,
                'quote_id' => $quoteId,
                'errors' => $result->getErrors(),
            ]);
            $message = isset(current($result->getErrors())['message'])
                ? __(current($result->getErrors())['message'])
                : __('The payment cannot be authorized.');
            throw new LocalizedException($message);
        }

        $body = $result->getBody();
        $this->checkoutOrderTracer->trace('auth_full_success', [
            'public_order_id' => $publicOrderId,
            'website_id' => $websiteId,
            'quote_id' => $quoteId,
            'transaction_id' => $body['data']['transactions'][0]['transaction_id'] ?? null,
        ]);

        return $body;
    }
}
