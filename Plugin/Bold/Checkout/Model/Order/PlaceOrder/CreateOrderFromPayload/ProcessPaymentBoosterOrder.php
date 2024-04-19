<?php

namespace Bold\CheckoutPaymentBooster\Plugin\Bold\Checkout\Model\Order\PlaceOrder\CreateOrderFromPayload;

use Bold\Checkout\Api\Data\PlaceOrder\Request\OrderDataInterface;
use Bold\Checkout\Model\Order\PlaceOrder\CreateOrderFromPayload;
use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\Order\PlaceOrder\ProcessOrder;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Process Payment Booster order.
 */
class ProcessPaymentBoosterOrder
{
    private $config;

    private $processOrder;

    /**
     * @param Config $config
     * @param ProcessOrder $processOrder
     */
    public function __construct(
        Config       $config,
        ProcessOrder $processOrder
    ) {
        $this->config = $config;
        $this->processOrder = $processOrder;
    }

    /**
     * Process Payment Booster order.
     *
     * @param CreateOrderFromPayload $subject
     * @param callable $proceed
     * @param OrderDataInterface $orderPayload
     * @param CartInterface $quote
     * @return OrderInterface
     * @throws \Exception
     */
    public function aroundCreateOrder(
        CreateOrderFromPayload $subject,
        callable               $proceed,
        OrderDataInterface     $orderPayload,
        CartInterface          $quote
    ): OrderInterface {
        $websiteId = (int)$quote->getStore()->getWebsiteId();

        return $this->config->isPaymentBoosterEnabled($websiteId)
            ? $this->processOrder->process($orderPayload)
            : $proceed($orderPayload, $quote);
    }
}
