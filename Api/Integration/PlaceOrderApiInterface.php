<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Integration;

/**
 * Place order from Bold Checkout integration quote.
 */
interface PlaceOrderApiInterface
{
    /**
     * Place order from integration quote with payment information.
     *
     * @param string $shopId
     * @param string $quoteMaskId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\PlaceOrderResponseInterface
     */
    public function placeOrder(
        string $shopId,
        string $quoteMaskId
    ): \Bold\CheckoutPaymentBooster\Api\Data\Integration\PlaceOrderResponseInterface;
}

