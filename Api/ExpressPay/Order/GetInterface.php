<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\ExpressPay\Order;

use Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\OrderInterface;

interface GetInterface
{
    /**
     * @param string $orderId
     * @param string $gatewayId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\OrderInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute($orderId, $gatewayId): OrderInterface;
}
