<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Integration;

/**
 * Update Quote Customer Information API Interface.
 */
interface UpdateQuoteCustomerInfoApiInterface
{
    /**
     * Update quote customer information, billing address, and/or shipping address.
     *
     * @param string $shopId
     * @param string $quoteMaskId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\UpdateQuoteCustomerInfoResponseInterface
     */
    public function updateCustomerInfo(
        string $shopId,
        string $quoteMaskId
    ): \Bold\CheckoutPaymentBooster\Api\Data\Integration\UpdateQuoteCustomerInfoResponseInterface;
}

