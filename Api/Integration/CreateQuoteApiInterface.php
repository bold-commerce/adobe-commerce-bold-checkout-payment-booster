<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Integration;

/**
 * Validate Bold Checkout API Integration.
 */
interface CreateQuoteApiInterface
{
    /**
     * Validate Bold Checkout API Integration.
     *
     * @param string $shopId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\CreateQuoteResponseInterface
     */
    public function createQuote(
        string $shopId,
    ): \Bold\CheckoutPaymentBooster\Api\Data\Integration\CreateQuoteResponseInterface;
}
