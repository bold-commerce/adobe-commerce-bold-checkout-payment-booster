<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Bold\CheckoutPaymentBooster\Model\PaymentBooster\FlowService;
use Exception;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * Init Bold order from quote.
 */
class InitOrderFromQuote
{
    private const INIT_SIMPLE_ORDER_URI = '/checkout_sidekick/{{shopId}}/order';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var BoldClient
     */
    private $client;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var FlowService
     */
    private $flowService;

    /**
     * @param Config $config
     * @param BoldClient $client
     * @param Json $json
     * @param FlowService $flowService
     */
    public function __construct(
        Config $config,
        BoldClient $client,
        Json $json,
        FlowService $flowService
    ) {
        $this->config = $config;
        $this->client = $client;
        $this->json = $json;
        $this->flowService = $flowService;
    }

    /**
     * Initialize order from quote.
     *
     * @param CartInterface&Quote $quote
     * @return array{
     *     data: array{
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
     *         public_order_id: string,
     *         should_vault: bool,
     *     }
     * }
     * @throws Exception
     */
    public function init(CartInterface $quote): array
    {
        $websiteId = (int)$quote->getStore()->getWebsiteId();
        $flowId = $this->config->getBoldBoosterFlowID($websiteId);
        if (!$flowId) {
            $flowId = $this->flowService->createAndSetBoldBoosterFlowID($websiteId);
        }
        /** @var \Magento\Customer\Api\Data\CustomerInterface $customer */
        $customer = $quote->getCustomer();
        $body = [
            'flow_id' => $flowId,
            'order_type' => 'simple_order',
            'cart_id' => $quote->getId() ?? '',
            'customer' => [
                'platform_id' => $customer->getId() ? (string)$quote->getCustomerId() : null,
            ],
        ];
        $orderData = $this->client->post(
            (int)$websiteId,
            self::INIT_SIMPLE_ORDER_URI,
            $body
        );
        if ($orderData->getErrors() || !isset($orderData->getBody()['data']['public_order_id'])) {
            $errorMessage = $orderData->getErrors()
                ? $this->json->serialize($orderData->getErrors())
                : 'Unknown error';
            throw new Exception(
                'Cannot initialize order, quote id: ' . $quote->getId() . ', error: ' . $errorMessage
            );
        }
        return $orderData->getBody();
    }
}
