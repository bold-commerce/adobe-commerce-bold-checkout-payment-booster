<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\ExpressPay\Order;

use Bold\Checkout\Api\Http\ClientInterface;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

use function __;
use function implode;

/**
 * @api
 */
class Get
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var ClientInterface
     */
    private $httpClient;

    public function __construct(StoreManagerInterface $storeManager, ClientInterface $httpClient)
    {
        $this->storeManager = $storeManager;
        $this->httpClient = $httpClient;
    }

    /**
     * @param string $paypalOrderId
     * @param string $gatewayId
     * @return array
     * @phpstan-return array{
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     shipping_address: array{
     *         address_line_1: string,
     *         address_line_2: string,
     *         city: string,
     *         country: string,
     *         province: string,
     *         postal_code: string,
     *         phone: string
     *     },
     *     billing_address: array{
     *         address_line_1: string,
     *         address_line_2: string,
     *         city: string,
     *         country: string,
     *         province: string,
     *         postal_code: string,
     *         phone: string
     *     }
     * }
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute($paypalOrderId, $gatewayId): array
    {
        $websiteId = (int)$this->storeManager->getStore()->getWebsiteId();
        $uri = "/checkout/orders/{{shopId}}/wallet_pay/$paypalOrderId?gateway_id=$gatewayId";

        try {
            $result = $this->httpClient->get($websiteId, $uri);
        } catch (Exception $exception) {
            throw new LocalizedException(
                __('Could not get Express Pay order. Error: "%1"', $exception->getMessage())
            );
        }

        $errors = $result->getErrors();

        if (count($errors) > 0) {
            throw new LocalizedException(
                __('Could not get Express Pay order. Errors: "%1"', implode(', ', $errors))
            );
        }

        if ($result->getStatus() !== 200) {
            throw new LocalizedException(
                __('An unknown error occurred while getting the Express Pay order.')
            );
        }

        return $result->getBody(); // @phpstan-ignore return.type
    }
}
