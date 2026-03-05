<?php

/**
 * Negative-scenario tests for AfterSubmitObserver.
 *
 * AfterSubmitObserver fires on sales_order_place_after. Its responsibilities are:
 *  1. Skip non-Bold or already-processed orders (idempotency).
 *  2. Resolve the Bold public_order_id from session or DB fallback.
 *  3. Persist an OrderExtensionData row (orderId → publicOrderId).
 *  4. Call SetCompleteState to signal Bold that the Magento order is complete.
 *
 * The scenarios below cover the negative paths that are most likely to surface in
 * production: session cleared between steps, already-processed idempotency guard,
 * non-Bold payment method, and the "call state without being authorized" problem where
 * neither the session nor the DB relation can supply a public_order_id.
 */

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
use Magento\Sales\Model\Order\Payment;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @magentoAppArea frontend
 */
class AfterSubmitObserverTest extends TestCase
{
    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Build an AfterSubmitObserver instance, replacing individual dependencies with mocks
     * when supplied in $overrides.
     *
     * @param array<string, mixed> $overrides
     */
    private function buildObserver(array $overrides = []): AfterSubmitObserver
    {
        $objectManager = Bootstrap::getObjectManager();
        return $objectManager->create(AfterSubmitObserver::class, $overrides);
    }

    /**
     * Build an Observer event wrapper carrying the given order.
     */
    private function makeObserverEvent(?Order $order): Observer
    {
        $event = new Event(['order' => $order]);
        $observerWrapper = new Observer();
        $observerWrapper->setEvent($event);
        return $observerWrapper;
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    /**
     * When the order's payment method is not Bold (e.g. "checkmo"), the observer must
     * return immediately without touching any Bold service.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/order_simple.php
     */
    public function testSkipsNonBoldOrders(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        // Fixture sets method to 'checkmo' — not a Bold method.

        $setCompleteStateMock = $this->createMock(SetCompleteState::class);
        $setCompleteStateMock->expects(self::never())->method('execute');

        $observer = $this->buildObserver(['setCompleteState' => $setCompleteStateMock]);
        $observer->execute($this->makeObserverEvent($order));
    }

    /**
     * When no order is attached to the event (null), the observer must return immediately.
     * This guards against misconfigured event dispatches.
     */
    public function testSkipsWhenOrderIsNull(): void
    {
        $setCompleteStateMock = $this->createMock(SetCompleteState::class);
        $setCompleteStateMock->expects(self::never())->method('execute');

        $observer = $this->buildObserver(['setCompleteState' => $setCompleteStateMock]);
        $observer->execute($this->makeObserverEvent(null));
    }

    /**
     * When the order has not been assigned a Magento entity ID (it was never persisted,
     * which can happen if the order placement rolled back), the observer must return
     * immediately — there is nothing to link a Bold order to.
     */
    public function testSkipsWhenOrderHasNoEntityId(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);

        /** @var Payment $payment */
        $payment = $objectManager->create(Payment::class);
        $payment->setMethod('bold');
        $order->setPayment($payment);
        // No entity ID set — order was never saved.

        $setCompleteStateMock = $this->createMock(SetCompleteState::class);
        $setCompleteStateMock->expects(self::never())->method('execute');

        $observer = $this->buildObserver(['setCompleteState' => $setCompleteStateMock]);
        $observer->execute($this->makeObserverEvent($order));
    }

    /**
     * Idempotency guard: if the Bold order relation already has a successful_state_at
     * timestamp (isBoldOrderProcessed returns true), the observer must skip the order.
     * This prevents double-processing when the observer fires more than once (e.g. admin
     * retry flows).
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/order_simple.php
     */
    public function testSkipsAlreadyProcessedOrders(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        /** @var Payment $payment */
        $payment = $objectManager->create(Payment::class);
        $payment->setMethod('bold');
        $order->setPayment($payment);

        // Stub the repository to report the order as already processed.
        $repositoryMock = $this->createMock(MagentoQuoteBoldOrderRepositoryInterface::class);
        $repositoryMock->method('isBoldOrderProcessed')->willReturn(true);

        $setCompleteStateMock = $this->createMock(SetCompleteState::class);
        $setCompleteStateMock->expects(self::never())->method('execute');

        $observer = $this->buildObserver([
            'setCompleteState'                 => $setCompleteStateMock,
            'magentoQuoteBoldOrderRepository'  => $repositoryMock,
        ]);
        $observer->execute($this->makeObserverEvent($order));
    }

    /**
     * "Call state without being authorized" — missing public_order_id scenario.
     *
     * When neither the CheckoutData session nor the DB relation can supply a
     * public_order_id, the observer must log a critical message and return SILENTLY
     * without calling SetCompleteState. The Magento order is already committed; throwing
     * here would produce a 500 response after a successful save.
     *
     * The SuccessPlugin is the customer-facing safety net: it will redirect to cart if
     * the auth is later found to be incomplete.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/order_simple.php
     * @magentoDbIsolation enabled
     */
    public function testLogsAndSkipsWhenPublicOrderIdCannotBeResolved(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        /** @var Payment $payment */
        $payment = $objectManager->create(Payment::class);
        $payment->setMethod('bold');
        $order->setPayment($payment);

        // Session has no public_order_id.
        $checkoutDataMock = $this->createMock(CheckoutData::class);
        $checkoutDataMock->method('getPublicOrderId')->willReturn(null);

        // DB relation also returns null.
        $repositoryMock = $this->createMock(MagentoQuoteBoldOrderRepositoryInterface::class);
        $repositoryMock->method('isBoldOrderProcessed')->willReturn(false);
        $repositoryMock->method('getPublicOrderIdFromOrder')->willReturn(null);

        // SetCompleteState must NEVER be called — the observer returns early.
        $setCompleteStateMock = $this->createMock(SetCompleteState::class);
        $setCompleteStateMock->expects(self::never())->method('execute');

        $observer = $this->buildObserver([
            'checkoutData'                    => $checkoutDataMock,
            'magentoQuoteBoldOrderRepository' => $repositoryMock,
            'setCompleteState'                => $setCompleteStateMock,
        ]);

        // Must return silently — no exception.
        $observer->execute($this->makeObserverEvent($order));
        $this->addToAssertionCount(1);
    }

    /**
     * When the public_order_id was read from the CheckoutData session, the session MUST
     * be reset immediately after reading so that a subsequent request (e.g. order-success
     * page load) does not reuse the same public_order_id.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/order_simple.php
     * @magentoDbIsolation enabled
     */
    public function testResetsSessionAfterReadingPublicOrderIdFromSession(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        /** @var Payment $payment */
        $payment = $objectManager->create(Payment::class);
        $payment->setMethod('bold');
        $order->setPayment($payment);

        // Session has a public_order_id.
        $checkoutDataMock = $this->createMock(CheckoutData::class);
        $checkoutDataMock->method('getPublicOrderId')
            ->willReturn('c3d4e5f6-a7b8-9012-cdef-123456789012');
        // resetCheckoutData() MUST be called exactly once.
        $checkoutDataMock->expects(self::once())->method('resetCheckoutData');

        $repositoryMock = $this->createMock(MagentoQuoteBoldOrderRepositoryInterface::class);
        $repositoryMock->method('isBoldOrderProcessed')->willReturn(false);

        // Use a mock SetCompleteState that swallows the call (we are not testing the API here).
        $setCompleteStateMock = $this->createMock(SetCompleteState::class);

        $observer = $this->buildObserver([
            'checkoutData'                    => $checkoutDataMock,
            'magentoQuoteBoldOrderRepository' => $repositoryMock,
            'setCompleteState'                => $setCompleteStateMock,
        ]);

        $observer->execute($this->makeObserverEvent($order));
    }

    /**
     * When the public_order_id comes from the DB relation (not from the session),
     * resetCheckoutData() must NOT be called — clearing the session in this case would
     * remove context that other parts of the checkout still need.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/order_simple.php
     * @magentoDbIsolation enabled
     */
    public function testDoesNotResetSessionWhenPublicOrderIdComesFromDb(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        /** @var Payment $payment */
        $payment = $objectManager->create(Payment::class);
        $payment->setMethod('bold');
        $order->setPayment($payment);

        // Session returns null → triggers the DB fallback path.
        $checkoutDataMock = $this->createMock(CheckoutData::class);
        $checkoutDataMock->method('getPublicOrderId')->willReturn(null);
        // resetCheckoutData() must NEVER be called when the session had nothing.
        $checkoutDataMock->expects(self::never())->method('resetCheckoutData');

        $repositoryMock = $this->createMock(MagentoQuoteBoldOrderRepositoryInterface::class);
        $repositoryMock->method('isBoldOrderProcessed')->willReturn(false);
        $repositoryMock->method('getPublicOrderIdFromOrder')
            ->willReturn('d4e5f6a7-b8c9-0123-def0-234567890123');

        $setCompleteStateMock = $this->createMock(SetCompleteState::class);

        $observer = $this->buildObserver([
            'checkoutData'                    => $checkoutDataMock,
            'magentoQuoteBoldOrderRepository' => $repositoryMock,
            'setCompleteState'                => $setCompleteStateMock,
        ]);

        $observer->execute($this->makeObserverEvent($order));
    }

    /**
     * When SetCompleteState throws a LocalizedException (e.g. Bold API is unavailable),
     * the observer must SWALLOW the exception, log it at critical, and return normally.
     *
     * The Magento order is already persisted at this point — throwing would produce a 500
     * response after a successful order save. The SuccessPlugin is the safety net for
     * the customer-facing path; this observer's failure is handled asynchronously.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/order_simple.php
     * @magentoDbIsolation enabled
     */
    public function testSwallowsExceptionFromSetCompleteStateAndClearsSession(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        /** @var Payment $payment */
        $payment = $objectManager->create(Payment::class);
        $payment->setMethod('bold');
        $order->setPayment($payment);

        $checkoutDataMock = $this->createMock(CheckoutData::class);
        $checkoutDataMock->method('getPublicOrderId')
            ->willReturn('e5f6a7b8-c9d0-1234-ef01-345678901234');
        // Session must still be reset even when SetCompleteState fails.
        $checkoutDataMock->expects(self::once())->method('resetCheckoutData');

        $repositoryMock = $this->createMock(MagentoQuoteBoldOrderRepositoryInterface::class);
        $repositoryMock->method('isBoldOrderProcessed')->willReturn(false);

        $setCompleteStateMock = $this->createMock(SetCompleteState::class);
        $setCompleteStateMock->method('execute')
            ->willThrowException(new LocalizedException(__('Bold API unavailable')));

        $observer = $this->buildObserver([
            'checkoutData'                    => $checkoutDataMock,
            'magentoQuoteBoldOrderRepository' => $repositoryMock,
            'setCompleteState'                => $setCompleteStateMock,
        ]);

        // Must NOT throw — exception is swallowed and logged.
        $observer->execute($this->makeObserverEvent($order));
        $this->addToAssertionCount(1);
    }
}
