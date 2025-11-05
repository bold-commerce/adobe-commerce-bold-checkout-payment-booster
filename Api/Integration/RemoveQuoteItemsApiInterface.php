<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Integration;

/**
 * Remove items from Bold Checkout integration quote.
 */
interface RemoveQuoteItemsApiInterface
{
    /**
     * Remove items from integration quote.
     *
     * @param string $shopId
     * @param string $quoteMaskId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\RemoveQuoteItemsResponseInterface
     */
    public function removeItems(
        string $shopId,
        string $quoteMaskId
    ): \Bold\CheckoutPaymentBooster\Api\Data\Integration\RemoveQuoteItemsResponseInterface;
}

