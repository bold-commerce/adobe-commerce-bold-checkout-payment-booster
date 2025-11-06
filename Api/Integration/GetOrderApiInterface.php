<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Integration;

/**
 * Get Order API Interface.
 */
interface GetOrderApiInterface
{
    /**
     * Get order by platform order ID (entity_id).
     *
     * @param string $shopId
     * @param string $platformOrderId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\GetOrderResponseInterface
     */
    public function getOrder(
        string $shopId,
        string $platformOrderId
    ): \Bold\CheckoutPaymentBooster\Api\Data\Integration\GetOrderResponseInterface;
}

