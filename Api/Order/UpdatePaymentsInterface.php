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
     * @param string $financialStatus
     * @param int $platformOrderId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\ResultInterface
     */
    public function update(
        string $shopId,
        string $financialStatus,
        int $platformOrderId
    ): ResultInterface;
}
