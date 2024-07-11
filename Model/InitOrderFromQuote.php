<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Bold\CheckoutPaymentBooster\Model\Order\InitOrderFromQuote\OrderDataProcessorInterface;
use Exception;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Init Bold order from quote.
 */
class InitOrderFromQuote
{
    private const INIT_SIMPLE_ORDER_URI = '/checkout_sidekick/{{shopId}}/order';

    /**
     * @var Flow
     */
    private $flow;

    /**
     * @var BoldClient
     */
    private $client;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var OrderDataProcessorInterface[]
     */
    private $orderDataProcessors;

    /**
     * @param Flow $flow
     * @param BoldClient $client
     * @param Json $json
     * @param array $orderDataProcessors
     */
    public function __construct(Flow $flow, BoldClient $client, Json $json, array $orderDataProcessors = [])
    {
        $this->flow = $flow;
        $this->client = $client;
        $this->json = $json;
        $this->orderDataProcessors = $orderDataProcessors;
    }

    /**
     * Initialize order from quote.
     *
     * @param CartInterface $quote
     * @return array
     * @throws Exception
     */
    public function init(CartInterface $quote): array
    {
        $body = [
            'flow_id' => $this->flow->getCheckoutFlowId($quote),
            'order_type' => 'simple_order',
            'cart_id' => $quote->getId(),
        ];
        $orderData = $this->client->post(
            (int)$quote->getStore()->getWebsiteId(),
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
        $boldCheckoutData = $orderData->getBody();
        foreach ($this->orderDataProcessors as $processor) {
            $boldCheckoutData = $processor->process($orderData->getBody(), $quote);
        }
        return $boldCheckoutData;
    }
}
