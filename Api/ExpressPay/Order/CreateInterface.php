<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\ExpressPay\Order;

interface CreateInterface
{
    /**
     * @param string|int $quoteMaskId
     * @param string $publicOrderId
     * @param string $gatewayId
     * @param string $shippingStrategy
     * @param bool $shouldVault
     * @param string $paymentSource
     * @return string[]
     * @phpstan-return array{order_id: string}
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute($quoteMaskId, $publicOrderId, $gatewayId, $shippingStrategy, $shouldVault, $paymentSource): array;
}
