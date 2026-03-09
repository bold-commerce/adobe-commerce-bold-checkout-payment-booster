<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Observer\Order;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Order\CheckPaymentMethod;
use Bold\CheckoutPaymentBooster\Model\Order\HydrateOrderFromQuote;
use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\TransactionComment;
use Bold\CheckoutPaymentBooster\Observer\Order\BeforePlaceObserver;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests for BeforePlaceObserver.
 *
 * Covers:
 *  - saveTransactionData: throws when transaction ID is missing/null, succeeds when present
 *  - Hydrate-before-auth guard: throws when successful_hydrate_at is not recorded after hydration
 *
 * @magentoAppArea frontend
 */
class BeforePlaceObserverTest extends TestCase
{
    // ─── helpers ──────────────────────────────────────────────────────────────

    /**
     * Build a BeforePlaceObserver with the given dependency overrides.
     *
     * @param array<string, mixed> $overrides
     */
    private function buildObserver(array $overrides = []): BeforePlaceObserver
    {
        return Bootstrap::getObjectManager()->create(BeforePlaceObserver::class, $overrides);
    }

    private function buildObserverEvent(Order $order): Observer
    {
        $event = new Event(['order' => $order]);
        $observer = new Observer();
        $observer->setEvent($event);
        return $observer;
    }

    // ─── saveTransactionData ──────────────────────────────────────────────────

    /**
     * When the Bold authorization API returns a response with no transaction ID,
     * saveTransactionData must throw a LocalizedException and block the order.
     * Previously this was a silent return which allowed the order to proceed without
     * a transaction record.
     */
    public function testSaveTransactionDataThrowsWhenTransactionIdIsMissing(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var BeforePlaceObserver $observer */
        $observer = $objectManager->create(BeforePlaceObserver::class);

        /** @var Payment|MockObject $payment */
        $payment = $this->createMock(Payment::class);

        /** @var Order|MockObject $order */
        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);

        $transactionDataWithNoTransactionId = [
            'data' => [
                'transactions' => []
            ]
        ];

        $method = new ReflectionMethod(BeforePlaceObserver::class, 'saveTransactionData');
        $method->setAccessible(true);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/no transaction ID/i');

        $method->invoke($observer, $order, $transactionDataWithNoTransactionId);
    }

    /**
     * When the Bold authorization API returns a response with a valid transaction ID,
     * saveTransactionData must set the transaction on the payment object without throwing.
     */
    public function testSaveTransactionDataSetsTransactionIdWhenPresent(): void
    {
        // Mock transactionComment so OrderRepository::save() is never called on the mock Order.
        // PHP 8 raises an Error (not Exception) when getItems() is called on null, which escapes
        // the catch (\Exception) block inside TransactionComment::addComment().
        $observer = $this->buildObserver([
            'transactionComment' => $this->createMock(TransactionComment::class),
        ]);

        /** @var Payment|MockObject $payment */
        $payment = $this->createMock(Payment::class);
        $payment->expects(self::once())->method('setTransactionId')->with('txn_abc123');
        $payment->expects(self::once())->method('setIsTransactionClosed')->with(false);
        $payment->expects(self::once())->method('addTransaction');

        /** @var Order|MockObject $order */
        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);

        $transactionData = [
            'data' => [
                'transactions' => [
                    [
                        'transaction_id' => 'txn_abc123',
                        'tender_details'  => [
                            'account' => '4111',
                            'email'   => 'test@example.com',
                        ],
                    ]
                ]
            ]
        ];

        $method = new ReflectionMethod(BeforePlaceObserver::class, 'saveTransactionData');
        $method->setAccessible(true);

        // Must not throw
        $method->invoke($observer, $order, $transactionData);
    }

    /**
     * When the auth response has a null transaction_id key explicitly,
     * saveTransactionData must also throw (null is treated the same as missing).
     */
    public function testSaveTransactionDataThrowsWhenTransactionIdIsNull(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var BeforePlaceObserver $observer */
        $observer = $objectManager->create(BeforePlaceObserver::class);

        /** @var Order|MockObject $order */
        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($this->createMock(Payment::class));

        $transactionDataWithNullId = [
            'data' => [
                'transactions' => [
                    ['transaction_id' => null]
                ]
            ]
        ];

        $method = new ReflectionMethod(BeforePlaceObserver::class, 'saveTransactionData');
        $method->setAccessible(true);

        $this->expectException(LocalizedException::class);

        $method->invoke($observer, $order, $transactionDataWithNullId);
    }

    // ─── hydrate-before-auth guard ────────────────────────────────────────────

    /**
     * After hydrate() is called, if the relation record does NOT have a
     * successful_hydrate_at timestamp the observer must throw a LocalizedException
     * and refuse to proceed to authorization.
     *
     * This guards against a silent hydration failure where the API call returned
     * an error but saveHydratedAt() was never called.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testThrowsWhenHydrateTimestampNotRecordedAfterHydration(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        $order->getPayment()->setMethod('bold');

        // Resolve quoteId from the fixture relation record via the known bold_order_id.
        /** @var MagentoQuoteBoldOrderRepositoryInterface $repo */
        $repo = $objectManager->create(MagentoQuoteBoldOrderRepositoryInterface::class);
        $relation = $repo->getByQuoteId(
            (string) $objectManager->create(\Bold\CheckoutPaymentBooster\Model\MagentoQuoteBoldOrder::class)
                ->load('e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0', 'bold_order_id')
                ->getQuoteId()
        );

        // Ensure successful_hydrate_at is null so the guard triggers.
        /** @var \Bold\CheckoutPaymentBooster\Model\ResourceModel\MagentoQuoteBoldOrder $resource */
        $resource = $objectManager->create(
            \Bold\CheckoutPaymentBooster\Model\ResourceModel\MagentoQuoteBoldOrder::class
        );
        $relation->setData('successful_hydrate_at', null);
        $resource->save($relation);

        $quoteId = $relation->getQuoteId();

        // Mock HydrateOrderFromQuote to be a no-op (we want to test the guard after hydration)
        /** @var HydrateOrderFromQuote|MockObject $hydrateOrderFromQuote */
        $hydrateOrderFromQuote = $this->createMock(HydrateOrderFromQuote::class);

        // Mock CartRepository to return a real Quote for the quoteId
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        $quote->load($quoteId);

        /** @var CartRepositoryInterface|MockObject $cartRepository */
        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('get')->willReturn($quote);

        // Mock CheckPaymentMethod to recognize 'bold' as a Bold method
        /** @var CheckPaymentMethod|MockObject $checkPaymentMethod */
        $checkPaymentMethod = $this->createMock(CheckPaymentMethod::class);
        $checkPaymentMethod->method('isBold')->willReturn(true);

        // Mock CheckoutData to return a known publicOrderId
        /** @var CheckoutData|MockObject $checkoutData */
        $checkoutData = $this->createMock(CheckoutData::class);
        $checkoutData->method('getPublicOrderId')
            ->willReturn('e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0');

        /** @var HydrateOrderFromQuote|MockObject $hydrateOrderFromQuote */
        $hydrateOrderFromQuote = $this->createMock(HydrateOrderFromQuote::class);

        $order->setQuoteId($quoteId);

        $observer = $this->buildObserver([
            'cartRepository'        => $cartRepository,
            'checkPaymentMethod'    => $checkPaymentMethod,
            'checkoutData'          => $checkoutData,
            'hydrateOrderFromQuote' => $hydrateOrderFromQuote,
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/order hydration did not complete/i');

        $observer->execute($this->buildObserverEvent($order));
    }

}
