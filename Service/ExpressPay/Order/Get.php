<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\ExpressPay\Order;

use Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\Order\AddressInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\OrderInterface;
use Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\OrderInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\ExpressPay\Order\GetInterface;
use Bold\CheckoutPaymentBooster\Api\Http\ClientInterface;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

use function __;
use function array_column;
use function array_key_exists;
use function implode;
use function is_array;

class Get implements GetInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var ClientInterface
     */
    private $httpClient;
    /**
     * @var OrderInterfaceFactory
     */
    private $orderFactory;
    /**
     * @var AddressInterfaceFactory
     */
    private $addressFactory;

    public function __construct(
        StoreManagerInterface $storeManager,
        ClientInterface $httpClient,
        OrderInterfaceFactory $orderFactory,
        AddressInterfaceFactory $addressFactory
    ) {
        $this->storeManager = $storeManager;
        $this->httpClient = $httpClient;
        $this->orderFactory = $orderFactory;
        $this->addressFactory = $addressFactory;
    }

    public function execute($orderId, $gatewayId): OrderInterface
    {
        $websiteId = (int)$this->storeManager->getStore()->getWebsiteId();
        $uri = "checkout/orders/{{shopId}}/wallet_pay/$orderId?gateway_id=$gatewayId";

        try {
            $result = $this->httpClient->get($websiteId, $uri);
        } catch (Exception $exception) {
            throw new LocalizedException(
                __('Could not get Express Pay order. Error: "%1"', $exception->getMessage())
            );
        }

        $errors = $result->getErrors();

        if (count($errors) > 0) {
            if (is_array($errors[0])) {
                $exceptionMessage = __(
                    'Could not get Express Pay order. Errors: "%1"',
                    implode(', ', array_column($errors, 'message'))
                );
            } else {
                $exceptionMessage = __('Could not get Express Pay order. Error: "%1"', $errors[0]);
            }

            throw new LocalizedException($exceptionMessage);
        }

        /** @var array{
         *     data?: array{
         *         first_name: string,
         *         last_name: string,
         *         email: string,
         *         shipping_address: array{
         *             address_line_1: string,
         *             address_line_2: string,
         *             city: string,
         *             country: string,
         *             province: string,
         *             postal_code: string,
         *             phone: string
         *         },
         *         billing_address: array{
         *             address_line_1: string,
         *             address_line_2: string,
         *             city: string,
         *             country: string,
         *             province: string,
         *             postal_code: string,
         *             phone: string
         *         }
         *     }
         * } $resultBody */
        $resultBody = $result->getBody();

        if ($result->getStatus() !== 200 || !array_key_exists('data', $resultBody)) {
            throw new LocalizedException(
                __('An unknown error occurred while getting the Express Pay order.')
            );
        }

        $orderData = $resultBody['data'];
        $orderData['shipping_address'] = $this->addressFactory->create(
            [
                'data' => $orderData['shipping_address']
            ]
        );
        $orderData['billing_address'] = $this->addressFactory->create(
            [
                'data' => $orderData['billing_address']
            ]
        );
        $order = $this->orderFactory->create(
            [
                'data' => $orderData
            ]
        );

        return $order;
    }
}
