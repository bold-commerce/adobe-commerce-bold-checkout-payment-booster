<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Integration;

/**
 * Add items to Bold Checkout integration quote.
 */
interface AddQuoteItemsApiInterface
{
    /**
     * Add items to integration quote.
     *
     * @param string $shopId
     * @param string $quoteMaskId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\AddQuoteItemsResponseInterface
     */
    public function addItems(
        string $shopId,
        string $quoteMaskId
    ): \Bold\CheckoutPaymentBooster\Api\Data\Integration\AddQuoteItemsResponseInterface;
}

