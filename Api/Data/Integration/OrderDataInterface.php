<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Integration;

/**
 * Order data interface for place order response.
 */
interface OrderDataInterface
{
    /**
     * Get platform order ID.
     *
     * @return string
     */
    public function getPlatformOrderId(): string;

    /**
     * Set platform order ID.
     *
     * @param string $platformOrderId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\OrderDataInterface
     */
    public function setPlatformOrderId(string $platformOrderId): \Bold\CheckoutPaymentBooster\Api\Data\Integration\OrderDataInterface;

    /**
     * Get platform friendly ID (increment ID).
     *
     * @return string
     */
    public function getPlatformFriendlyId(): string;

    /**
     * Set platform friendly ID (increment ID).
     *
     * @param string $platformFriendlyId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\OrderDataInterface
     */
    public function setPlatformFriendlyId(string $platformFriendlyId): \Bold\CheckoutPaymentBooster\Api\Data\Integration\OrderDataInterface;

    /**
     * Get order object.
     *
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    public function getOrder(): \Magento\Sales\Api\Data\OrderInterface;

    /**
     * Set order object.
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\OrderDataInterface
     */
    public function setOrder(\Magento\Sales\Api\Data\OrderInterface $order): \Bold\CheckoutPaymentBooster\Api\Data\Integration\OrderDataInterface;
}

