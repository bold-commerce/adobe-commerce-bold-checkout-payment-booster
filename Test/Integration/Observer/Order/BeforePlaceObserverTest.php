<?php

/**
 * Negative-scenario tests for BeforePlaceObserver.
 *
 * BeforePlaceObserver fires on sales_order_place_before. It is the last sync step
 * before Magento persists the order. Its job is to:
 *  1. Hydrate the Bold order with final Magento quote data.
 *  2. POST payments/auth/full to Bold (the "auth" step).
 *  3. Record the AUTH transaction on the Magento OrderPayment.
 *
 * Any failure at step 1 or 2 MUST abort order placement by re-throwing the exception —
 * the Magento order must not be committed unless Bold has confirmed authorization.
 *
 * The scenarios below focus on what happens when the auth call fails ("auth/full
 * failing"), when hydration fails, when the transaction_id is absent from the
 * authorization response, and when the order is not a Bold order at all.
 */

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Observer\Order;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Order\HydrateOrderFromQuote;
use Bold\CheckoutPaymentBooster\Model\Payment\Authorize;
use Bold\CheckoutPaymentBooster\Observer\Order\BeforePlaceObserver;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea frontend
 */
class BeforePlaceObserverTest extends TestCase
{
    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Build a BeforePlaceObserver with specific dependencies replaced by mocks.
     *
     * @param array<string, mixed> $overrides
     */
    private function buildObserver(array $overrides = []): BeforePlaceObserver
    {
        return Bootstrap::getObjectManager()->create(BeforePlaceObserver::class, $overrides);
    }

    /**
     * Wrap an order in an Observer event the same way Magento does.
     */
    private function makeObserverEvent(Order $order): Observer
    {
        $event = new Event(['order' => $order]);
        $observerWrapper = new Observer();
        $observerWrapper->setEvent($event);
        return $observerWrapper;
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    /**
     * When the order payment method is not Bold, the observer must do nothing.
     * Non-Bold orders must never touch the Bold API.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/order_simple.php
     */
    public function testSkipsNonBoldOrders(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001'); // payment method is 'checkmo' from fixture

        $authorizeMock = $this->createMock(Authorize::class);
        $authorizeMock->expects(self::never())->method('execute');

        $hydrateMock = $this->createMock(HydrateOrderFromQuote::class);
        $hydrateMock->expects(self::never())->method('hydrate');

        $observer = $this->buildObserver([
            'authorize'            => $authorizeMock,
            'hydrateOrderFromQuote' => $hydrateMock,
        ]);
        $observer->execute($this->makeObserverEvent($order));
    }

    /**
     * "auth/full failing" — the Bold authorization API returns an error.
     *
     * When Authorize::execute() throws a LocalizedException (e.g. the payment was
     * declined, the Bold API is down, or the JWT has expired), BeforePlaceObserver
     * MUST propagate the exception. This aborts Magento's order placement transaction
     * so the order is never persisted, protecting both the merchant and the customer.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @magentoDbIsolation enabled
     */
    public function testThrowsWhenAuthFullFails(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        // Load a quote that has a Bold order relation (created by the fixture).
        $quote = $this->loadFixtureQuote();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var Payment $payment */
        $payment = $objectManager->create(Payment::class);
        $payment->setMethod('bold');
        $order->setPayment($payment);
        $order->setQuoteId($quote->getId());

        // Hydration succeeds.
        $hydrateMock = $this->createMock(HydrateOrderFromQuote::class);

        // Authorization fails — this is the core "auth/full failing" scenario.
        $authorizeMock = $this->createMock(Authorize::class);
        $authorizeMock->method('execute')
            ->willThrowException(new LocalizedException(__('Payment was declined by Bold')));

        $observer = $this->buildObserver([
            'authorize'            => $authorizeMock,
            'hydrateOrderFromQuote' => $hydrateMock,
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/payment was declined by bold/i');

        $observer->execute($this->makeObserverEvent($order));
    }

    /**
     * When HydrateOrderFromQuote::hydrate() fails (e.g. the Bold order data could not
     * be sent because the API is unreachable or returned a validation error), the
     * observer MUST propagate the exception to abort order placement.
     *
     * Authorization must also NOT be called if hydration already failed.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @magentoDbIsolation enabled
     */
    public function testThrowsWhenHydrationFails(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        $quote = $this->loadFixtureQuote();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var Payment $payment */
        $payment = $objectManager->create(Payment::class);
        $payment->setMethod('bold');
        $order->setPayment($payment);
        $order->setQuoteId($quote->getId());

        // Hydration fails before authorization is attempted.
        $hydrateMock = $this->createMock(HydrateOrderFromQuote::class);
        $hydrateMock->method('hydrate')
            ->willThrowException(
                new LocalizedException(__('Could not hydrate Bold order: address validation failed'))
            );

        // Authorization must never be reached.
        $authorizeMock = $this->createMock(Authorize::class);
        $authorizeMock->expects(self::never())->method('execute');

        $observer = $this->buildObserver([
            'authorize'            => $authorizeMock,
            'hydrateOrderFromQuote' => $hydrateMock,
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/could not hydrate bold order/i');

        $observer->execute($this->makeObserverEvent($order));
    }

    /**
     * When the auth/full response contains no transaction_id (e.g. the payment gateway
     * did not return one, or the response shape is unexpected), saveTransactionData()
     * must return early without adding an AUTH transaction to the order payment.
     *
     * The observer must NOT throw — a missing transaction_id is handled gracefully because
     * some payment flows (e.g. wallets) may not produce one at authorization time.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @magentoDbIsolation enabled
     */
    public function testDoesNotSaveTransactionWhenTransactionIdIsMissing(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        $quote = $this->loadFixtureQuote();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var Payment $payment */
        $payment = $objectManager->create(Payment::class);
        $payment->setMethod('bold');
        $order->setPayment($payment);
        $order->setQuoteId($quote->getId());

        $hydrateMock = $this->createMock(HydrateOrderFromQuote::class);

        // Auth response has no transaction_id.
        $authorizeMock = $this->createMock(Authorize::class);
        $authorizeMock->method('execute')
            ->willReturn(['data' => ['transactions' => []]]);

        $repositoryMock = $this->createMock(MagentoQuoteBoldOrderRepositoryInterface::class);

        $observer = $this->buildObserver([
            'authorize'                       => $authorizeMock,
            'hydrateOrderFromQuote'           => $hydrateMock,
            'magentoQuoteBoldOrderRepository' => $repositoryMock,
        ]);

        $observer->execute($this->makeObserverEvent($order));

        self::assertNull(
            $payment->getTransactionId(),
            'No AUTH transaction should be recorded when transaction_id is absent from the auth response'
        );
    }

    /**
     * When the auth/full response contains a transaction_id, saveTransactionData() must
     * attach a TYPE_AUTH transaction to the order payment so the Magento transaction grid
     * reflects the Bold authorization.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @magentoDbIsolation enabled
     */
    public function testSavesTransactionDataWhenTransactionIdIsPresent(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        $quote = $this->loadFixtureQuote();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var Payment $payment */
        $payment = $objectManager->create(Payment::class);
        $payment->setMethod('bold');
        $order->setPayment($payment);
        $order->setQuoteId($quote->getId());

        $hydrateMock = $this->createMock(HydrateOrderFromQuote::class);

        $authorizeMock = $this->createMock(Authorize::class);
        $authorizeMock->method('execute')
            ->willReturn([
                'data' => [
                    'transactions' => [
                        [
                            'transaction_id' => 'bold-txn-abc123',
                            'tender_details' => [
                                'account' => '4242',
                                'email'   => 'buyer@example.com',
                            ],
                        ],
                    ],
                ],
            ]);

        $repositoryMock = $this->createMock(MagentoQuoteBoldOrderRepositoryInterface::class);

        $observer = $this->buildObserver([
            'authorize'                       => $authorizeMock,
            'hydrateOrderFromQuote'           => $hydrateMock,
            'magentoQuoteBoldOrderRepository' => $repositoryMock,
        ]);

        $observer->execute($this->makeObserverEvent($order));

        self::assertSame(
            'bold-txn-abc123',
            $payment->getTransactionId(),
            'Payment transaction ID must be set from the Bold auth response'
        );
        self::assertFalse(
            (bool) $payment->getIsTransactionClosed(),
            'AUTH transaction must be left open (not captured yet)'
        );
    }

    // ── fixture helper ────────────────────────────────────────────────────────

    /**
     * Load the quote created by magento_quote_bold_order.php.
     *
     * @return \Magento\Quote\Model\Quote
     */
    private function loadFixtureQuote(): \Magento\Quote\Model\Quote
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var \Magento\Quote\Model\QuoteFactory $quoteFactory */
        $quoteFactory = $objectManager->get(\Magento\Quote\Model\QuoteFactory::class);
        /** @var \Magento\Quote\Model\ResourceModel\Quote $quoteResource */
        $quoteResource = $objectManager->get(\Magento\Quote\Model\ResourceModel\Quote::class);

        $quote = $quoteFactory->create();
        $quoteResource->load($quote, 'test_order_item_with_items', 'reserved_order_id');

        return $quote;
    }
}
