<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Model\Order\UpdatePayments;

use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\ChangeOrderStatus;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for ChangeOrderStatus.
 *
 * Verifies that:
 *  - The order's status is updated to the supplied value
 *  - A descriptive comment is added to the order's status history
 *  - OrderRepository::save() is called exactly once
 *
 * @magentoAppArea adminhtml
 */
class ChangeOrderStatusTest extends TestCase
{
    /**
     * The order status must be updated to the value passed to execute().
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testSetsOrderStatusToSuppliedValue(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        /** @var OrderRepositoryInterface|MockObject $orderRepository */
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->method('save');

        /** @var ChangeOrderStatus $service */
        $service = $objectManager->create(ChangeOrderStatus::class, [
            'orderRepository' => $orderRepository,
        ]);

        $service->execute($order, 'on_hold');

        self::assertSame('on_hold', $order->getStatus());
    }

    /**
     * A comment attributing the status change to Bold Payment Booster must be appended
     * to the order's status history so merchants can see why the status changed.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testAddsExplanatoryCommentToStatusHistory(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        /** @var OrderRepositoryInterface|MockObject $orderRepository */
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);

        /** @var ChangeOrderStatus $service */
        $service = $objectManager->create(ChangeOrderStatus::class, [
            'orderRepository' => $orderRepository,
        ]);

        $service->execute($order, 'fraud');

        $history = $order->getStatusHistories();
        self::assertNotEmpty($history);

        $latestComment = end($history);
        self::assertStringContainsString(
            'Bold Payment Booster',
            (string) $latestComment->getComment(),
            'Comment must mention Bold Payment Booster so merchants know the source of the change.'
        );
    }

    /**
     * OrderRepository::save() must be called exactly once to persist the status change.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testSavesOrderExactlyOnce(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        /** @var OrderRepositoryInterface|MockObject $orderRepository */
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->expects(self::once())->method('save')->with($order);

        /** @var ChangeOrderStatus $service */
        $service = $objectManager->create(ChangeOrderStatus::class, [
            'orderRepository' => $orderRepository,
        ]);

        $service->execute($order, 'pending');
    }

    /**
     * The comment on the status history entry must carry the new status value
     * so that the history record is linked to the correct status.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testStatusHistoryEntryCarriesNewStatus(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        /** @var OrderRepositoryInterface|MockObject $orderRepository */
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);

        /** @var ChangeOrderStatus $service */
        $service = $objectManager->create(ChangeOrderStatus::class, [
            'orderRepository' => $orderRepository,
        ]);

        $service->execute($order, 'holded');

        $history = $order->getStatusHistories();
        $latestEntry = end($history);

        self::assertSame('holded', $latestEntry->getStatus());
    }
}
