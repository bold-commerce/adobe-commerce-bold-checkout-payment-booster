<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Integration;

/**
 * Update item quantities in Bold Checkout integration quote.
 */
interface UpdateQuoteItemsApiInterface
{
    /**
     * Update item quantities in integration quote.
     *
     * @param string $shopId
     * @param string $quoteMaskId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\UpdateQuoteItemsResponseInterface
     */
    public function updateItems(
        string $shopId,
        string $quoteMaskId
    ): \Bold\CheckoutPaymentBooster\Api\Data\Integration\UpdateQuoteItemsResponseInterface;
}

