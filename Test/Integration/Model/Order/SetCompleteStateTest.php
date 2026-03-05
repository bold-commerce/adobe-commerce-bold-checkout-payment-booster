<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Model\Order;

use Bold\CheckoutPaymentBooster\Model\MagentoQuoteBoldOrder;
use Bold\CheckoutPaymentBooster\Model\Order\SetCompleteState;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\MagentoQuoteBoldOrder as MagentoQuoteBoldOrderResourceModel;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the dual-check authorization guard in SetCompleteState::execute().
 *
 * PREMISE: setCompleteState can only be called if the order has been authorized.
 * Two independent signals must BOTH confirm authorization:
 *   1. bold_booster_magento_quote_bold_order.successful_auth_full_at (Bold lifecycle table)
 *   2. sales_payment_transaction AUTH row (Magento standard table)
 *
 * @magentoAppArea frontend
 */
class SetCompleteStateTest extends TestCase
{
    /**
     * When no Bold order relation record exists for the order's quote,
     * SetCompleteState must throw — we have no evidence authorization happened.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testThrowsWhenNoBoldOrderRelationExists(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        $order->setQuoteId('99999990'); // no relation record for this quote

        /** @var SetCompleteState $setCompleteState */
        $setCompleteState = $objectManager->create(SetCompleteState::class);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/no Bold order relation record found/i');

        $setCompleteState->execute($order);
    }

    /**
     * When a Bold order relation record exists but successful_auth_full_at is null,
     * SetCompleteState must throw — authorization was never recorded.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testThrowsWhenAuthTimestampIsNull(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var MagentoQuoteBoldOrder $relation */
        $relation = $objectManager->create(MagentoQuoteBoldOrder::class);
        /** @var MagentoQuoteBoldOrderResourceModel $resource */
        $resource = $objectManager->create(MagentoQuoteBoldOrderResourceModel::class);
        $resource->load(
            $relation,
            'e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0',
            'bold_order_id'
        );

        $relation->setData('successful_auth_full_at', null);
        $resource->save($relation);

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        $order->setQuoteId($relation->getQuoteId());

        /** @var SetCompleteState $setCompleteState */
        $setCompleteState = $objectManager->create(SetCompleteState::class);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/payment authorization has not been recorded/i');

        $setCompleteState->execute($order);
    }

    /**
     * When bold_auth_full_at IS set but NO Magento AUTH transaction exists,
     * SetCompleteState must throw — the cross-check catches a partial failure where
     * BeforePlaceObserver saved the Bold timestamp but saveTransactionData() did not
     * commit the transaction row to sales_payment_transaction.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testThrowsWhenBoldAuthSetButNoMagentoTransaction(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var MagentoQuoteBoldOrder $relation */
        $relation = $objectManager->create(MagentoQuoteBoldOrder::class);
        /** @var MagentoQuoteBoldOrderResourceModel $resource */
        $resource = $objectManager->create(MagentoQuoteBoldOrderResourceModel::class);
        $resource->load(
            $relation,
            'e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0',
            'bold_order_id'
        );

        $relation->setData('successful_auth_full_at', '2026-01-01 00:00:00');
        $resource->save($relation);

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        $order->setQuoteId($relation->getQuoteId());
        // Order fixture (100000001) has no payment transactions in its fixture data

        /** @var SetCompleteState $setCompleteState */
        $setCompleteState = $objectManager->create(SetCompleteState::class);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/no AUTH transaction found in sales_payment_transaction/i');

        $setCompleteState->execute($order);
    }

    /**
     * When BOTH checks pass (bold_auth_full_at set AND Magento AUTH transaction exists),
     * the guard passes and the method proceeds to call the Bold API.
     * We verify the guard did not throw by checking the exception message does NOT
     * match any guard message (any remaining exception is from the HTTP client).
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @magentoDataFixture Magento/Sales/_files/order_with_invoice.php
     */
    public function testBothChecksPassWhenAuthTimestampSetAndTransactionExists(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var MagentoQuoteBoldOrder $relation */
        $relation = $objectManager->create(MagentoQuoteBoldOrder::class);
        /** @var MagentoQuoteBoldOrderResourceModel $resource */
        $resource = $objectManager->create(MagentoQuoteBoldOrderResourceModel::class);
        $resource->load(
            $relation,
            'e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0',
            'bold_order_id'
        );

        $relation->setData('successful_auth_full_at', '2026-01-01 00:00:00');
        $resource->save($relation);

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        $order->setQuoteId($relation->getQuoteId());

        // Manually create an AUTH transaction for the order so Check 2 passes
        /** @var \Magento\Sales\Model\Order\Payment\Transaction $transaction */
        $transaction = $objectManager->create(\Magento\Sales\Model\Order\Payment\Transaction::class);
        $transaction->setOrderId((int) $order->getEntityId());
        $transaction->setPaymentId((int) $order->getPayment()->getEntityId());
        $transaction->setTxnId('test-auth-txn-001');
        $transaction->setTxnType(\Magento\Sales\Api\Data\TransactionInterface::TYPE_AUTH);
        $objectManager->get(\Magento\Sales\Api\TransactionRepositoryInterface::class)->save($transaction);

        /** @var SetCompleteState $setCompleteState */
        $setCompleteState = $objectManager->create(SetCompleteState::class);

        try {
            $setCompleteState->execute($order);
        } catch (LocalizedException $e) {
            $guardMessages = [
                'payment authorization has not been recorded',
                'no Bold order relation record found',
                'no AUTH transaction found in sales_payment_transaction',
            ];
            foreach ($guardMessages as $guardMsg) {
                self::assertStringNotContainsStringIgnoringCase(
                    $guardMsg,
                    $e->getMessage(),
                    sprintf('Guard should NOT have thrown for: %s', $guardMsg)
                );
            }
        }
    }
}
