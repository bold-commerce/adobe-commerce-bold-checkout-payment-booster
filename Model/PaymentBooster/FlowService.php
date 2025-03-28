<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\PaymentBooster;

use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Magento\Framework\Exception\LocalizedException;
use Bold\CheckoutPaymentBooster\Model\Config;

use function is_array;

class FlowService
{
    private const FLOW_CREATE_URL = 'checkout/shop/{{shopId}}/flows';
    private const DEFAULT_FLOW_NAME = 'Bold Booster for Paypal';
    private const DEFAULT_FLOW_ID = 'bold-booster-m2';
    private const FLOW_ALREADY_EXISTS_ERROR = 'flow.flow_already_exists';
    private const DEFAULT_FLOW_TYPE = 'custom';

    /**
     * @var BoldClient
     */
    private $boldClient;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param BoldClient $boldClient
     * @param Config $config
     */
    public function __construct(
        BoldClient $boldClient,
        Config $config
    ) {
        $this->boldClient = $boldClient;
        $this->config = $config;
    }

    /**
     * Create a new flow.
     *
     * @param int $websiteId
     * @return string
     * @throws LocalizedException
     */
    public function createAndSetBoldBoosterFlowID(int $websiteId): string
    {
        $body = [
            'flow_name' => self::DEFAULT_FLOW_NAME,
            'flow_id' => self::DEFAULT_FLOW_ID,
            'flow_type' => self::DEFAULT_FLOW_TYPE,
        ];
        $result = $this->boldClient->post($websiteId, self::FLOW_CREATE_URL, $body);

        if ($result->getErrors()) {
            if (
                is_array($result->getErrors()[0])
                && $result->getErrors()[0]['type'] === self::FLOW_ALREADY_EXISTS_ERROR
            ) {
                $this->config->setBoldBoosterFlowID($websiteId, self::DEFAULT_FLOW_ID);
                return self::DEFAULT_FLOW_ID;
            }
            $message = isset(current($result->getErrors())['message'])
                ? __(current($result->getErrors())['message'])
                : __('Something went wrong while setting up Payment Booster. Please Try Again. If the error persists please contact Bold Support.');
            throw new LocalizedException($message);
        }
        $flowId = $result->getBody()['data']['flows'][0]['flow_id'] ?? null;
        if (!$flowId) {
            throw new LocalizedException(__('Something went wrong while setting up Payment Booster. Please Try Again. If the error persists please contact Bold Support.'));
        }
        $this->config->setBoldBoosterFlowID($websiteId, $flowId);
        return $flowId;
    }
}
