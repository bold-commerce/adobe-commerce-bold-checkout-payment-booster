<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Integration;

/**
 * Get Quote API Interface.
 */
interface GetQuoteApiInterface
{
    /**
     * Get quote by mask ID with recalculated totals and shipping methods.
     *
     * @param string $shopId
     * @param string $quoteMaskId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\GetQuoteResponseInterface
     */
    public function getQuote(
        string $shopId,
        string $quoteMaskId
    ): \Bold\CheckoutPaymentBooster\Api\Data\Integration\GetQuoteResponseInterface;
}

