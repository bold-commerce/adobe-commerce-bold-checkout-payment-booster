<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Order;

use Magento\Quote\Api\Data\AddressInterface;

/**
 * Hydrate Bold order from Magento quote.
 */
interface GuestHydrateOrderInterface
{
    /**
     * Hydrate Bold simple order with Magento quote data
     *
     * @param string $shopId
     * @param string $cartId
     * @param string $publicOrderId
     * @param \Magento\Quote\Api\Data\AddressInterface $address
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function hydrate(string $shopId, string $cartId, string $publicOrderId, AddressInterface $address): void;
}
