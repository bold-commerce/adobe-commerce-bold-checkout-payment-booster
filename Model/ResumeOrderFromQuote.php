<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Bold\CheckoutPaymentBooster\Model\Quote\GetQuoteExtensionData;
use Exception;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Resume Bold order from quote.
 */
class ResumeOrderFromQuote
{
    private const RESUME_SIMPLE_ORDER_URI = '/checkout_sidekick/{{shopId}}/order/%s/resume';

    /**
     * @var BoldClient
     */
    private $client;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var GetQuoteExtensionData
     */
    private $getQuoteExtensionData;

    /**
     * @param BoldClient $client
     * @param Json $json
     * @param GetQuoteExtensionData $getQuoteExtensionData
     */
    public function __construct(
        BoldClient            $client,
        Json                  $json,
        GetQuoteExtensionData $getQuoteExtensionData
    ) {
        $this->client = $client;
        $this->json = $json;
        $this->getQuoteExtensionData = $getQuoteExtensionData;
    }

    /**
     * Resume order from quote.
     *
     * @param CartInterface $quote
     * @return array
     * @throws Exception
     */
    public function resume(CartInterface $quote): ?array
    {
        $quoteExtensionData = $this->getQuoteExtensionData->execute((int)$quote->getId());
        $publicOrderId = $quoteExtensionData ? $quoteExtensionData->getPublicOrderId() : null;
        if (!$publicOrderId) {
            return null;
        }

        $orderData = $this->client->post(
            (int)$quote->getStore()->getWebsiteId(),
            sprintf(self::RESUME_SIMPLE_ORDER_URI, $publicOrderId),
            []
        );

        if ($orderData->getErrors() || !isset($orderData->getBody()['data']['public_order_id'])) {
            $errorMessage = $orderData->getErrors()
                ? $this->json->serialize($orderData->getErrors())
                : 'Unknown error';
            throw new Exception(
                'Cannot resume order, quote id: ' . $quote->getId() . ', error: ' . $errorMessage
            );
        }

        // TODO: return $orderData->getBody() after the 'resume' endpoint provides flow_settings.
        $result = $orderData->getBody();
        $quoteExtensionDataFlowSettings = $quoteExtensionData->getFlowSettings();
        if (!isset($result['data']['flow_settings']) && empty($quoteExtensionDataFlowSettings)) {
            return null;
        }
        if (!isset($result['data']['flow_settings'])) {
            $result['data']['flow_settings'] = $quoteExtensionDataFlowSettings;
        }

        return $result;
    }
}
