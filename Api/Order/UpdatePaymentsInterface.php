<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Order;

use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\ResultInterface;

/**
 * Update order payment service.
 */
interface UpdatePaymentsInterface
{
    /**
     * Update order payment.
     *
     * @param string $shopId
     * @param string $publicOrderId
     * @param string $platformFriendlyId
     * @param string $financialStatus
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\ResultInterface
     */
    public function update(string $shopId, string $publicOrderId, string $platformFriendlyId, string $financialStatus): ResultInterface;
}
