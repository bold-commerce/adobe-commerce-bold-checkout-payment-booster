<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\Payment;

use Bold\Checkout\Api\Http\ClientInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Fully authorize payments.
 */
class Authorize
{
    private const PATH_PAYMENTS_AUTH = 'checkout/orders/{{shopId}}/%s/payments/auth/full';

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Fully authorize payments.
     *
     * @param string $publicOrderId
     * @param int $websiteId
     * @return array
     * @throws LocalizedException
     */
    public function execute(string $publicOrderId, int $websiteId): array
    {
        $url = sprintf(self::PATH_PAYMENTS_AUTH, $publicOrderId);
        $result = $this->client->post($websiteId, $url, []);
        if ($result->getErrors()) {
            $message = isset(current($result->getErrors())['message'])
                ? __(current($result->getErrors())['message'])
                : __('The payment cannot be authorized.');
            throw new LocalizedException($message);
        }

        return $result->getBody();
    }
}
