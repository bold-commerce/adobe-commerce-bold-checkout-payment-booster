<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Cancel order service.
 */
class ChangeOrderStatus
{
    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * Change order status
     *
     * @param Order $order
     * @param string $status
     * @return void
     */
    public function execute(Order $order, string $status): void
    {
        try {
            $order->setStatus($status);
            $order->addCommentToStatusHistory(
                'Order not captured, status automatically changed by Bold Payment Booster',
                $status
            );
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());
        }
        $this->orderRepository->save($order);
    }
}
