<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;

/**
 * Cancel order service.
 */
class CancelOrder
{
    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @param OrderManagementInterface $orderManagement
     */
    public function __construct(
        OrderManagementInterface $orderManagement
    ) {
        $this->orderManagement = $orderManagement;
    }

    /**
     * Cancel order.
     * TODO: test and update after the cancel request from Bold is implemented.
     *
     * @param OrderInterface $order
     * @return void
     */
    public function execute(OrderInterface $order): void
    {
        $this->orderManagement->cancel((int)$order->getEntityId());
    }
}
