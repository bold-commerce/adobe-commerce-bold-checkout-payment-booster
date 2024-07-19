<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Order;

use Magento\Quote\Api\Data\AddressInterface;

/**
 * Hydrate Bold order from Magento quote.
 */
interface HydrateOrderInterface
{
    /**
     * Hydrate Bold simple order with Magento quote data
     *
     * @param string $shopId
     * @param string $publicOrderId
     * @param \Magento\Quote\Api\Data\AddressInterface $address
     * @return void
     */
    public function hydrate(string $shopId, string $publicOrderId, AddressInterface $address): void;
}
