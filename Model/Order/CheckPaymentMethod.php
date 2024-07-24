<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Magento\Sales\Api\Data\OrderInterface;

/**
 * Check if the order payment method is bold-related.
 */
class CheckPaymentMethod
{
    /**
     * @var array
     */
    private $boldPaymentMethods;

    /**
     * @param array $boldPaymentMethods
     */
    public function __construct(array $boldPaymentMethods = [])
    {
        $this->boldPaymentMethods = $boldPaymentMethods;
    }

    /**
     * Check if the order payment method is bold-related.
     *
     * @param OrderInterface $order
     * @return bool
     */
    public function isBold(OrderInterface $order): bool
    {
        $paymentMethod = $order->getPayment()->getMethod();

        return in_array($paymentMethod, $this->boldPaymentMethods);
    }
}
