<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterface;

/**
 * Validate Bold Checkout API Integration.
 */
interface ValidateApiInterface
{
    /**
     * Validate Bold Checkout API Integration.
     *
     * @param string $shopId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterface
     */
    public function validate(
        string $shopId,
    ): ResultInterface;
}
