<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Order\Payment;

/**
 * Update payments result data interface.
 */
interface ResultInterface
{
    /**
     * Get order id.
     *
     * @return string
     */
    public function getPlatformId(): string;

    /**
     * Get order increment id.
     *
     * @return string
     */
    public function getPlatformFriendlyId(): string;

    /**
     * Get customer id.
     *
     * @return string|null
     */
    public function getPlatformCustomerId(): ?string;
}
