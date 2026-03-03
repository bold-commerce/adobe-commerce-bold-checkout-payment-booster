<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Model\Order\UpdatePayments;

use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\CancelOrder;
use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\ChangeOrderStatus;
use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\TransactionComment;
use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionData;
use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for CancelOrder — verifies the delayed-capture branching logic.
 *
 * CancelOrder::execute() has three exclusive routing paths controlled by Config:
 *
 *  1. Delayed capture DISABLED             → cancel the order (regular flow)
 *  2. Delayed capture ENABLED + cancel flag → cancel the order
 *  3. Delayed capture ENABLED + status flag → change the order status (do NOT cancel)
 *  4. Delayed capture ENABLED + no flag    → no action taken
 *
 * @magentoAppArea adminhtml
 */
class CancelOrderTest extends TestCase
{
    private function buildService(array $overrides = []): CancelOrder
    {
        return Bootstrap::getObjectManager()->create(CancelOrder::class, $overrides);
    }

    /**
     * Build shared mocks that are needed in every test to prevent DB/API side effects.
     *
     * @return array{config: Config&MockObject, orderManagement: OrderManagementInterface&MockObject, changeOrderStatus: ChangeOrderStatus&MockObject, extensionDataRepo: OrderExtensionDataRepository&MockObject, transactionComment: TransactionComment&MockObject}
     */
    private function buildBaseMocks(): array
    {
        $extensionData = $this->createMock(OrderExtensionData::class);

        /** @var OrderExtensionDataRepository|MockObject $extensionDataRepo */
        $extensionDataRepo = $this->createMock(OrderExtensionDataRepository::class);
        $extensionDataRepo->method('getByOrderId')->willReturn($extensionData);

        return [
            'config'             => $this->createMock(Config::class),
            'orderManagement'    => $this->createMock(OrderManagementInterface::class),
            'changeOrderStatus'  => $this->createMock(ChangeOrderStatus::class),
            'extensionDataRepo'  => $extensionDataRepo,
            'transactionComment' => $this->createMock(TransactionComment::class),
        ];
    }

    /**
     * Path 1: when delayed capture is DISABLED, cancel() must be called once
     * and ChangeOrderStatus must never be invoked.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testCancelsOrderWhenDelayedCaptureIsDisabled(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        $mocks = $this->buildBaseMocks();

        $mocks['config']->method('isDelayedCaptureEnabled')->willReturn(false);
        $mocks['config']->method('isDelayedCaptureCancelOrder')->willReturn(false);
        $mocks['config']->method('isDelayedCaptureChangeOrderStatus')->willReturn(false);

        $mocks['orderManagement']->expects(self::once())->method('cancel');
        $mocks['changeOrderStatus']->expects(self::never())->method('execute');

        $service = $this->buildService([
            'config'                       => $mocks['config'],
            'orderManagement'              => $mocks['orderManagement'],
            'changeOrderStatus'            => $mocks['changeOrderStatus'],
            'orderExtensionDataRepository' => $mocks['extensionDataRepo'],
            'transactionComment'           => $mocks['transactionComment'],
        ]);

        $service->execute($order);
    }

    /**
     * Path 2: when delayed capture is ENABLED and the cancel-order sub-flag is true,
     * cancel() must be called once and ChangeOrderStatus must NOT be invoked.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testCancelsOrderWhenDelayedCaptureEnabledAndCancelFlagSet(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        $mocks = $this->buildBaseMocks();

        $mocks['config']->method('isDelayedCaptureEnabled')->willReturn(true);
        $mocks['config']->method('isDelayedCaptureCancelOrder')->willReturn(true);
        $mocks['config']->method('isDelayedCaptureChangeOrderStatus')->willReturn(false);

        $mocks['orderManagement']->expects(self::once())->method('cancel');
        $mocks['changeOrderStatus']->expects(self::never())->method('execute');

        $service = $this->buildService([
            'config'                       => $mocks['config'],
            'orderManagement'              => $mocks['orderManagement'],
            'changeOrderStatus'            => $mocks['changeOrderStatus'],
            'orderExtensionDataRepository' => $mocks['extensionDataRepo'],
            'transactionComment'           => $mocks['transactionComment'],
        ]);

        $service->execute($order);
    }

    /**
     * Path 3: when delayed capture is ENABLED and the change-status sub-flag is true,
     * ChangeOrderStatus::execute() must be called with the configured new status,
     * and cancel() must NOT be called.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testChangesOrderStatusWhenDelayedCaptureEnabledAndChangeStatusFlagSet(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        $mocks = $this->buildBaseMocks();

        $mocks['config']->method('isDelayedCaptureEnabled')->willReturn(true);
        $mocks['config']->method('isDelayedCaptureCancelOrder')->willReturn(false);
        $mocks['config']->method('isDelayedCaptureChangeOrderStatus')->willReturn(true);
        $mocks['config']->method('isDelayedCaptureNewOrderStatus')->willReturn('on_hold');

        $mocks['orderManagement']->expects(self::never())->method('cancel');
        $mocks['changeOrderStatus']->expects(self::once())
            ->method('execute')
            ->with($order, 'on_hold');

        $service = $this->buildService([
            'config'                       => $mocks['config'],
            'orderManagement'              => $mocks['orderManagement'],
            'changeOrderStatus'            => $mocks['changeOrderStatus'],
            'orderExtensionDataRepository' => $mocks['extensionDataRepo'],
            'transactionComment'           => $mocks['transactionComment'],
        ]);

        $service->execute($order);
    }

    /**
     * Path 4: when delayed capture is ENABLED but neither sub-flag is set,
     * no action (cancel or status change) must be taken.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testTakesNoActionWhenDelayedCaptureEnabledButNeitherSubFlagSet(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        $mocks = $this->buildBaseMocks();

        $mocks['config']->method('isDelayedCaptureEnabled')->willReturn(true);
        $mocks['config']->method('isDelayedCaptureCancelOrder')->willReturn(false);
        $mocks['config']->method('isDelayedCaptureChangeOrderStatus')->willReturn(false);

        $mocks['orderManagement']->expects(self::never())->method('cancel');
        $mocks['changeOrderStatus']->expects(self::never())->method('execute');

        $service = $this->buildService([
            'config'                       => $mocks['config'],
            'orderManagement'              => $mocks['orderManagement'],
            'changeOrderStatus'            => $mocks['changeOrderStatus'],
            'orderExtensionDataRepository' => $mocks['extensionDataRepo'],
            'transactionComment'           => $mocks['transactionComment'],
        ]);

        $service->execute($order);
    }

    /**
     * When cancelOrder() throws, the isCancelInProgress flag must be reset to false
     * and the exception must be re-thrown so the caller is aware of the failure.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testResetsCancelInProgressFlagAndRethrowsWhenCancelFails(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        $extensionData = $this->createMock(OrderExtensionData::class);
        // Flag must be set to false after the exception
        $extensionData->expects(self::exactly(2))->method('setIsCancelInProgress')
            ->withConsecutive([true], [false]);

        /** @var OrderExtensionDataRepository|MockObject $extensionDataRepo */
        $extensionDataRepo = $this->createMock(OrderExtensionDataRepository::class);
        $extensionDataRepo->method('getByOrderId')->willReturn($extensionData);

        /** @var Config|MockObject $config */
        $config = $this->createMock(Config::class);
        $config->method('isDelayedCaptureEnabled')->willReturn(false);
        $config->method('isDelayedCaptureCancelOrder')->willReturn(false);
        $config->method('isDelayedCaptureChangeOrderStatus')->willReturn(false);

        /** @var OrderManagementInterface|MockObject $orderManagement */
        $orderManagement = $this->createMock(OrderManagementInterface::class);
        $orderManagement->method('cancel')->willThrowException(new \Exception('Cancel failed'));

        $service = $this->buildService([
            'config'                       => $config,
            'orderManagement'              => $orderManagement,
            'changeOrderStatus'            => $this->createMock(ChangeOrderStatus::class),
            'orderExtensionDataRepository' => $extensionDataRepo,
            'transactionComment'           => $this->createMock(TransactionComment::class),
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cancel failed');

        $service->execute($order);
    }
}
