<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Integration;

/**
 * Update Order Payments API Interface.
 */
interface UpdateOrderPaymentsApiInterface
{
    /**
     * Update order payment by platform order ID (entity_id).
     *
     * @param string $shopId
     * @param string $platformOrderId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\UpdateOrderPaymentsResponseInterface
     */
    public function updatePayments(
        string $shopId,
        string $platformOrderId
    ): \Bold\CheckoutPaymentBooster\Api\Data\Integration\UpdateOrderPaymentsResponseInterface;
}

