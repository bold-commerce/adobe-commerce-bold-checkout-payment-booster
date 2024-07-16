<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Order;

/**
 * Hydrate Bold order from Magento quote.
 */
interface HydrateOrderInterface
{
    /**
     * Hydrate Bold simple order with Magento quote data
     *
     * @param string $shopId
     * @param string $publicOrderId
     * @return void
     */
    public function hydrate(string $shopId, string $publicOrderId): void;
}
