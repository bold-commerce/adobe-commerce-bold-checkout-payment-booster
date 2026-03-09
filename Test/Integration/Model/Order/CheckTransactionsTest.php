<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Model\Order;

use Bold\CheckoutPaymentBooster\Model\MagentoQuoteBoldOrder;
use Bold\CheckoutPaymentBooster\Model\Order\CheckTransactions;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\MagentoQuoteBoldOrder as MagentoQuoteBoldOrderResource;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for CheckTransactions.
 *
 * CheckTransactions is the authorization guard introduced in CHK-9534 / CHK-9537.
 * It provides three independent cross-checks that SetCompleteState uses before it
 * calls the Bold API:
 *
 *  hasRelationRecord()             — the bold_booster_magento_quote_bold_order row exists
 *  getAuthTransactionFromLifecycle() — that row has a non-null successful_auth_full_at timestamp
 *  hasAuthTransaction()            — a Magento TYPE_AUTH row exists in sales_payment_transaction
 *
 * @magentoAppArea frontend
 */
class CheckTransactionsTest extends TestCase
{
    // ─── hasRelationRecord ────────────────────────────────────────────────────

    /**
     * Returns false when no relation record exists for the given quoteId.
     */
    public function testHasRelationRecordReturnsFalseWhenNoRecord(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var CheckTransactions $service */
        $service = $objectManager->create(CheckTransactions::class);

        self::assertFalse($service->hasRelationRecord('99999999'));
    }

    /**
     * Returns true when a relation record exists in bold_booster_magento_quote_bold_order.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     */
    public function testHasRelationRecordReturnsTrueWhenRecordExists(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var MagentoQuoteBoldOrder $relation */
        $relation = $objectManager->create(MagentoQuoteBoldOrder::class);
        $objectManager->create(MagentoQuoteBoldOrderResource::class)->load(
            $relation,
            'e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0',
            'bold_order_id'
        );

        /** @var CheckTransactions $service */
        $service = $objectManager->create(CheckTransactions::class);

        self::assertTrue($service->hasRelationRecord((string) $relation->getQuoteId()));
    }

    // ─── getAuthTransactionFromLifecycle ─────────────────────────────────────

    /**
     * Returns false when the relation record has a null successful_auth_full_at timestamp
     * (i.e. Bold has not recorded a successful authorization).
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     */
    public function testGetAuthTransactionFromLifecycleReturnsFalseWhenTimestampIsNull(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var MagentoQuoteBoldOrder $relation */
        $relation = $objectManager->create(MagentoQuoteBoldOrder::class);
        /** @var MagentoQuoteBoldOrderResource $resource */
        $resource = $objectManager->create(MagentoQuoteBoldOrderResource::class);
        $resource->load(
            $relation,
            'e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0',
            'bold_order_id'
        );

        $relation->setData('successful_auth_full_at', null);
        $resource->save($relation);

        /** @var CheckTransactions $service */
        $service = $objectManager->create(CheckTransactions::class);

        self::assertFalse($service->getAuthTransactionFromLifecycle((string) $relation->getQuoteId()));
    }

    /**
     * Returns true when the relation record has a non-null successful_auth_full_at timestamp.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     */
    public function testGetAuthTransactionFromLifecycleReturnsTrueWhenTimestampIsSet(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var MagentoQuoteBoldOrder $relation */
        $relation = $objectManager->create(MagentoQuoteBoldOrder::class);
        /** @var MagentoQuoteBoldOrderResource $resource */
        $resource = $objectManager->create(MagentoQuoteBoldOrderResource::class);
        $resource->load(
            $relation,
            'e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0',
            'bold_order_id'
        );

        $relation->setData('successful_auth_full_at', '2026-01-01 00:00:00');
        $resource->save($relation);

        /** @var CheckTransactions $service */
        $service = $objectManager->create(CheckTransactions::class);

        self::assertTrue($service->getAuthTransactionFromLifecycle((string) $relation->getQuoteId()));
    }

    /**
     * Returns false when the quoteId has no relation record at all
     * (covers the NoSuchEntityException branch inside getAuthTransactionFromLifecycle).
     */
    public function testGetAuthTransactionFromLifecycleReturnsFalseForUnknownQuote(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var CheckTransactions $service */
        $service = $objectManager->create(CheckTransactions::class);

        self::assertFalse($service->getAuthTransactionFromLifecycle('99999998'));
    }

    // ─── hasAuthTransaction ───────────────────────────────────────────────────

    /**
     * Returns false when the order has no AUTH transaction in sales_payment_transaction.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testHasAuthTransactionReturnsFalseWhenNoTransactionExists(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        /** @var CheckTransactions $service */
        $service = $objectManager->create(CheckTransactions::class);

        self::assertFalse($service->hasAuthTransaction($order));
    }

    /**
     * Returns false when the order has no entity ID (not yet persisted).
     * The method guards against this to avoid a full table-scan with a null filter.
     */
    public function testHasAuthTransactionReturnsFalseWhenOrderHasNoEntityId(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        // No entity ID set — transient, unsaved object

        /** @var CheckTransactions $service */
        $service = $objectManager->create(CheckTransactions::class);

        self::assertFalse($service->hasAuthTransaction($order));
    }

    /**
     * Returns true when a TYPE_AUTH transaction exists in sales_payment_transaction
     * for the given order.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testHasAuthTransactionReturnsTrueWhenAuthTransactionExists(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        // Manually create an AUTH transaction to satisfy the check
        /** @var \Magento\Sales\Model\Order\Payment\Transaction $transaction */
        $transaction = $objectManager->create(\Magento\Sales\Model\Order\Payment\Transaction::class);
        $transaction->setOrderId((int) $order->getEntityId());
        $transaction->setPaymentId((int) $order->getPayment()->getEntityId());
        $transaction->setTxnId('test-check-transactions-auth-' . uniqid());
        $transaction->setTxnType(TransactionInterface::TYPE_AUTH);
        $objectManager->get(TransactionRepositoryInterface::class)->save($transaction);

        /** @var CheckTransactions $service */
        $service = $objectManager->create(CheckTransactions::class);

        self::assertTrue($service->hasAuthTransaction($order));
    }

    /**
     * Returns false when the order only has a CAPTURE transaction (not AUTH).
     * Verifies the filter is strict about transaction type.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testHasAuthTransactionReturnsFalseWhenOnlyCaptureTransactionExists(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        /** @var \Magento\Sales\Model\Order\Payment\Transaction $transaction */
        $transaction = $objectManager->create(\Magento\Sales\Model\Order\Payment\Transaction::class);
        $transaction->setOrderId((int) $order->getEntityId());
        $transaction->setPaymentId((int) $order->getPayment()->getEntityId());
        $transaction->setTxnId('test-check-transactions-capture-' . uniqid());
        $transaction->setTxnType(TransactionInterface::TYPE_CAPTURE);
        $objectManager->get(TransactionRepositoryInterface::class)->save($transaction);

        /** @var CheckTransactions $service */
        $service = $objectManager->create(CheckTransactions::class);

        self::assertFalse($service->hasAuthTransaction($order));
    }
}
