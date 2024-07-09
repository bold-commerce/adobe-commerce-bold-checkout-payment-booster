<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Order;

/**
 * Hydrate Bold order from Magento quote.
 */
interface GuestHydrateOrderInterface
{
    /**
     * Hydrate Bold simple order with Magento quote data
     *
     * @param string $shopId
     * @param string $cartId
     * @param string $publicOrderId
     * @return void
     */
    public function hydrate(string $shopId, string $cartId, string $publicOrderId): void;
}
