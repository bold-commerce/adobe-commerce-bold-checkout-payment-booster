<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\ExpressPay\Order;

interface UpdateInterface
{
    /**
     * @param string|int $quoteMaskId
     * @param string $gatewayId
     * @param string $paypalOrderId
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute($quoteMaskId, $gatewayId, $paypalOrderId): void;
}
