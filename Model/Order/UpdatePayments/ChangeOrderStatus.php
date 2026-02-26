<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments;

use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Cancel order service.
 */
class ChangeOrderStatus
{
    const ACTION = 'Canceled';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var OrderManagementInterface
     */
    private $orderManagement;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param OrderManagementInterface $orderManagement
     * @param OrderRepositoryInterface $orderRepository
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        OrderManagementInterface $orderManagement,
        OrderRepositoryInterface $orderRepository,
        Config $config,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->orderManagement = $orderManagement;
        $this->orderRepository = $orderRepository;
        $this->logger = $logger;
    }

    /**
     * Change order status
     *
     * @param OrderInterface|Order $order
     * @param string $status
     * @return void
     */
    public function execute(OrderInterface $order, string $status): void
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
