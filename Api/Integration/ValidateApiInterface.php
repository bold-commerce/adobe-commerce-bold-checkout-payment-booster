<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Integration;

/**
 * Validate Bold Checkout API Integration.
 */
interface ValidateApiInterface
{
    /**
     * Validate Bold Checkout API Integration.
     *
     * @param string $shopId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\IntegrationResultInterface
     */
    public function validate(
        string $shopId,
    ): \Bold\CheckoutPaymentBooster\Api\Data\Integration\IntegrationResultInterface;
}
