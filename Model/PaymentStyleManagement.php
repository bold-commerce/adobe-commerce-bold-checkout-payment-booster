<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Bold\Checkout\Api\Http\ClientInterface;
use Bold\CheckoutPaymentBooster\Api\PaymentStyleManagementInterface;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Payment iframe styles management.
 */
class PaymentStyleManagement implements PaymentStyleManagementInterface
{
    private const ERROR_TYPE_STYLES_NOT_SET = 'payment_method_style_sheet.not_found';

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var Json
     */
    private $serializer;

    /**
     * @param ClientInterface $client
     * @param Json $serializer
     */
    public function __construct(
        ClientInterface $client,
        Json            $serializer
    ) {
        $this->client = $client;
        $this->serializer = $serializer;
    }

    /**
     * @inheritDoc
     */
    public function update(int $websiteId, array $data): void
    {
        $result = $this->client->post($websiteId, self::PAYMENT_CSS_API_URI, $data);
        if ($result->getErrors()) {
            $error = current($result->getErrors());
            if (is_array($error)) {
                $error = $this->serializer->serialize($error);
            }
            throw new \Exception($error);
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(int $websiteId): void
    {
        $result = $this->client->delete($websiteId, self::PAYMENT_CSS_API_URI, []);
        if ($result->getErrors()) {
            $error = current($result->getErrors());
            if (is_array($error)) {
                $error = $this->serializer->serialize($error);
            }
            throw new \Exception($error);
        }
    }

    /**
     * @inheritDoc
     */
    public function get(int $websiteId): array
    {
        $result = $this->client->get($websiteId, self::PAYMENT_CSS_API_URI);
        if ($result->getErrors()) {
            $error = current($result->getErrors());
            if (!isset($error['type']) || $error['type'] !== self::ERROR_TYPE_STYLES_NOT_SET) {
                if (is_array($error)) {
                    $error = $this->serializer->serialize($error);
                }
                throw new \Exception($error);
            }
        }

        return $result->getBody()['data']['style_sheet'] ?? [];
    }
}
