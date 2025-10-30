<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Integration;

/**
 * Set Quote Shipping Method API Interface.
 */
interface SetQuoteShippingMethodApiInterface
{
    /**
     * Set shipping method on quote.
     *
     * @param string $shopId
     * @param string $quoteMaskId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\SetQuoteShippingMethodResponseInterface
     */
    public function setShippingMethod(
        string $shopId,
        string $quoteMaskId
    ): \Bold\CheckoutPaymentBooster\Api\Data\Integration\SetQuoteShippingMethodResponseInterface;
}

