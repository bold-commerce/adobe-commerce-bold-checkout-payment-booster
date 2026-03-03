<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Observer\Order;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Order\CheckPaymentMethod;
use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionDataFactory;
use Bold\CheckoutPaymentBooster\Model\Order\SetCompleteState;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\Order\OrderExtensionData as OrderExtensionDataResource;
use Bold\CheckoutPaymentBooster\Observer\Order\AfterSubmitObserver;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Integration tests for AfterSubmitObserver.
 *
 * Covers all branching paths:
 *  - Skip for non-Bold orders
 *  - Skip when order has no entity ID
 *  - Skip when order was already processed
 *  - Critical log + early return when publicOrderId cannot be resolved
 *  - LocalizedException from SetCompleteState is caught (not rethrown)
 *  - resetCheckoutData() is called only when publicOrderId came from the session
 *  - resetCheckoutData() is NOT called when publicOrderId came only from the DB fallback
 *
 * @magentoAppArea frontend
 */
class AfterSubmitObserverTest extends TestCase
{
    // ─── helpers ──────────────────────────────────────────────────────────────

    /**
     * Build an AfterSubmitObserver with selected dependencies replaced by mocks.
     * Any argument not supplied keeps its real DI-resolved instance.
     *
     * @param array<string, mixed> $overrides
     */
    private function buildObserver(array $overrides = []): AfterSubmitObserver
    {
        $objectManager = Bootstrap::getObjectManager();

        return $objectManager->create(AfterSubmitObserver::class, $overrides);
    }

    /**
     * Build a minimal Magento event Observer that carries the given order.
     */
    private function buildObserverEvent(?Order $order): Observer
    {
        $event = new Event(['order' => $order]);
        $eventObserver = new Observer();
        $eventObserver->setEvent($event);
        return $eventObserver;
    }

    /**
     * When the order is not a Bold order, execute() must return without
     * touching any Bold-specific services.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testSkipsNonBoldOrders(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        $order->getPayment()->setMethod('checkmo'); // non-Bold method

        /** @var SetCompleteState|MockObject $setCompleteState */
        $setCompleteState = $this->createMock(SetCompleteState::class);
        $setCompleteState->expects(self::never())->method('execute');

        $observer = $this->buildObserver(['setCompleteState' => $setCompleteState]);
        $observer->execute($this->buildObserverEvent($order));
    }

    /**
     * When the order object is null the observer must not throw.
     */
    public function testSkipsWhenOrderIsNull(): void
    {
        $setCompleteState = $this->createMock(SetCompleteState::class);
        $setCompleteState->expects(self::never())->method('execute');

        $observer = $this->buildObserver(['setCompleteState' => $setCompleteState]);

        // Must not throw
        $observer->execute($this->buildObserverEvent(null));
    }

    /**
     * When the order has no entity ID (not yet persisted) the observer must skip.
     */
    public function testSkipsWhenOrderHasNoEntityId(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        // No entity ID — transient object
        $order->getPayment()->setMethod('bold');

        /** @var CheckPaymentMethod|MockObject $checkPaymentMethod */
        $checkPaymentMethod = $this->createMock(CheckPaymentMethod::class);
        $checkPaymentMethod->method('isBold')->willReturn(true);

        /** @var SetCompleteState|MockObject $setCompleteState */
        $setCompleteState = $this->createMock(SetCompleteState::class);
        $setCompleteState->expects(self::never())->method('execute');

        $observer = $this->buildObserver([
            'checkPaymentMethod' => $checkPaymentMethod,
            'setCompleteState'   => $setCompleteState,
        ]);

        $observer->execute($this->buildObserverEvent($order));
    }

    /**
     * When isBoldOrderProcessed() returns true the observer must skip silently —
     * this prevents SetCompleteState from being called a second time when
     * both AfterSubmitObserver and FallbackAfterSubmitObserver are triggered.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testSkipsAlreadyProcessedOrders(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        $order->getPayment()->setMethod('bold');

        /** @var CheckPaymentMethod|MockObject $checkPaymentMethod */
        $checkPaymentMethod = $this->createMock(CheckPaymentMethod::class);
        $checkPaymentMethod->method('isBold')->willReturn(true);

        /** @var MagentoQuoteBoldOrderRepositoryInterface|MockObject $repo */
        $repo = $this->createMock(MagentoQuoteBoldOrderRepositoryInterface::class);
        $repo->method('isBoldOrderProcessed')->willReturn(true);

        /** @var SetCompleteState|MockObject $setCompleteState */
        $setCompleteState = $this->createMock(SetCompleteState::class);
        $setCompleteState->expects(self::never())->method('execute');

        $observer = $this->buildObserver([
            'checkPaymentMethod'             => $checkPaymentMethod,
            'magentoQuoteBoldOrderRepository' => $repo,
            'setCompleteState'               => $setCompleteState,
        ]);

        $observer->execute($this->buildObserverEvent($order));
    }

    /**
     * When both the session AND the DB fallback return null for publicOrderId,
     * the observer must log a critical message and return early WITHOUT throwing
     * (the order is already committed — we must not cause a 500 response).
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testLogsAndSkipsWhenPublicOrderIdCannotBeResolved(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        $order->getPayment()->setMethod('bold');

        /** @var CheckPaymentMethod|MockObject $checkPaymentMethod */
        $checkPaymentMethod = $this->createMock(CheckPaymentMethod::class);
        $checkPaymentMethod->method('isBold')->willReturn(true);

        /** @var MagentoQuoteBoldOrderRepositoryInterface|MockObject $repo */
        $repo = $this->createMock(MagentoQuoteBoldOrderRepositoryInterface::class);
        $repo->method('isBoldOrderProcessed')->willReturn(false);
        $repo->method('getPublicOrderIdFromOrder')->willReturn(null); // DB also empty

        /** @var CheckoutData|MockObject $checkoutData */
        $checkoutData = $this->createMock(CheckoutData::class);
        $checkoutData->method('getPublicOrderId')->willReturn(null);  // session empty

        /** @var LoggerInterface|MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())->method('critical');    // must log critical

        /** @var SetCompleteState|MockObject $setCompleteState */
        $setCompleteState = $this->createMock(SetCompleteState::class);
        $setCompleteState->expects(self::never())->method('execute');

        $observer = $this->buildObserver([
            'checkPaymentMethod'             => $checkPaymentMethod,
            'magentoQuoteBoldOrderRepository' => $repo,
            'checkoutData'                   => $checkoutData,
            'logger'                         => $logger,
            'setCompleteState'               => $setCompleteState,
        ]);

        // Must NOT throw even though publicOrderId is unresolvable
        $observer->execute($this->buildObserverEvent($order));
    }

    /**
     * When SetCompleteState::execute() throws a LocalizedException (auth-before-setState guard),
     * the observer must catch it, log it as critical, and NOT rethrow it —
     * the order is already committed and a 500 response must be avoided.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testCatchesLocalizedExceptionFromSetCompleteState(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        $order->getPayment()->setMethod('bold');

        /** @var CheckPaymentMethod|MockObject $checkPaymentMethod */
        $checkPaymentMethod = $this->createMock(CheckPaymentMethod::class);
        $checkPaymentMethod->method('isBold')->willReturn(true);

        /** @var MagentoQuoteBoldOrderRepositoryInterface|MockObject $repo */
        $repo = $this->createMock(MagentoQuoteBoldOrderRepositoryInterface::class);
        $repo->method('isBoldOrderProcessed')->willReturn(false);
        $repo->method('getPublicOrderIdFromOrder')->willReturn('test-public-order-id');

        /** @var CheckoutData|MockObject $checkoutData */
        $checkoutData = $this->createMock(CheckoutData::class);
        $checkoutData->method('getPublicOrderId')->willReturn(null); // from DB, not session

        /** @var OrderExtensionDataResource|MockObject $extensionDataResource */
        $extensionDataResource = $this->createMock(OrderExtensionDataResource::class);

        /** @var SetCompleteState|MockObject $setCompleteState */
        $setCompleteState = $this->createMock(SetCompleteState::class);
        $setCompleteState->method('execute')
            ->willThrowException(new LocalizedException(__('Auth not recorded')));

        /** @var LoggerInterface|MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())->method('critical'); // must log the caught exception

        $observer = $this->buildObserver([
            'checkPaymentMethod'             => $checkPaymentMethod,
            'magentoQuoteBoldOrderRepository' => $repo,
            'checkoutData'                   => $checkoutData,
            'orderExtensionDataResource'     => $extensionDataResource,
            'setCompleteState'               => $setCompleteState,
            'logger'                         => $logger,
        ]);

        // Must NOT throw — exception is caught internally
        $observer->execute($this->buildObserverEvent($order));
    }

    /**
     * When publicOrderId comes from the checkout session, resetCheckoutData() MUST be called
     * after all work completes so the session is cleaned up.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testResetsSessionWhenPublicOrderIdCameFromSession(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        $order->getPayment()->setMethod('bold');

        /** @var CheckPaymentMethod|MockObject $checkPaymentMethod */
        $checkPaymentMethod = $this->createMock(CheckPaymentMethod::class);
        $checkPaymentMethod->method('isBold')->willReturn(true);

        /** @var MagentoQuoteBoldOrderRepositoryInterface|MockObject $repo */
        $repo = $this->createMock(MagentoQuoteBoldOrderRepositoryInterface::class);
        $repo->method('isBoldOrderProcessed')->willReturn(false);

        /** @var CheckoutData|MockObject $checkoutData */
        $checkoutData = $this->createMock(CheckoutData::class);
        $checkoutData->method('getPublicOrderId')->willReturn('session-public-order-id');
        $checkoutData->expects(self::once())->method('resetCheckoutData'); // must be called

        /** @var OrderExtensionDataResource|MockObject $extensionDataResource */
        $extensionDataResource = $this->createMock(OrderExtensionDataResource::class);

        /** @var SetCompleteState|MockObject $setCompleteState */
        $setCompleteState = $this->createMock(SetCompleteState::class);

        $observer = $this->buildObserver([
            'checkPaymentMethod'             => $checkPaymentMethod,
            'magentoQuoteBoldOrderRepository' => $repo,
            'checkoutData'                   => $checkoutData,
            'orderExtensionDataResource'     => $extensionDataResource,
            'setCompleteState'               => $setCompleteState,
        ]);

        $observer->execute($this->buildObserverEvent($order));
    }

    /**
     * When publicOrderId comes ONLY from the DB fallback (session was already cleared),
     * resetCheckoutData() must NOT be called — calling it would be a no-op at best and
     * could clear a fresh session that a concurrent request started.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testDoesNotResetSessionWhenPublicOrderIdCameOnlyFromDb(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        $order->getPayment()->setMethod('bold');

        /** @var CheckPaymentMethod|MockObject $checkPaymentMethod */
        $checkPaymentMethod = $this->createMock(CheckPaymentMethod::class);
        $checkPaymentMethod->method('isBold')->willReturn(true);

        /** @var MagentoQuoteBoldOrderRepositoryInterface|MockObject $repo */
        $repo = $this->createMock(MagentoQuoteBoldOrderRepositoryInterface::class);
        $repo->method('isBoldOrderProcessed')->willReturn(false);
        $repo->method('getPublicOrderIdFromOrder')->willReturn('db-public-order-id');

        /** @var CheckoutData|MockObject $checkoutData */
        $checkoutData = $this->createMock(CheckoutData::class);
        $checkoutData->method('getPublicOrderId')->willReturn(null); // session is empty
        $checkoutData->expects(self::never())->method('resetCheckoutData'); // must NOT be called

        /** @var OrderExtensionDataResource|MockObject $extensionDataResource */
        $extensionDataResource = $this->createMock(OrderExtensionDataResource::class);

        /** @var SetCompleteState|MockObject $setCompleteState */
        $setCompleteState = $this->createMock(SetCompleteState::class);

        $observer = $this->buildObserver([
            'checkPaymentMethod'             => $checkPaymentMethod,
            'magentoQuoteBoldOrderRepository' => $repo,
            'checkoutData'                   => $checkoutData,
            'orderExtensionDataResource'     => $extensionDataResource,
            'setCompleteState'               => $setCompleteState,
        ]);

        $observer->execute($this->buildObserverEvent($order));
    }
}
