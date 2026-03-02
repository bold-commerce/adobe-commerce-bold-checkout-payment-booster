<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Plugin\Checkout\Controller\Onepage;

use Bold\CheckoutPaymentBooster\Model\MagentoQuoteBoldOrder;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\MagentoQuoteBoldOrder as MagentoQuoteBoldOrderResourceModel;
use Bold\CheckoutPaymentBooster\Plugin\Checkout\Controller\Onepage\SuccessPlugin;
use Bold\CheckoutPaymentBooster\Test\Integration\_Assertions\AssertPluginIsConfiguredCorrectly;
use Magento\Checkout\Controller\Onepage\Success;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea frontend
 */
class SuccessPluginTest extends TestCase
{
    use AssertPluginIsConfiguredCorrectly;

    private const PLUGIN_NAME = 'bold_booster_verify_payment_authorized_on_success';

    public function testIsConfiguredCorrectlyInFrontend(): void
    {
        self::assertPluginIsConfiguredCorrectly(
            self::PLUGIN_NAME,
            SuccessPlugin::class,
            Success::class
        );
    }

    /**
     * When no Bold relation record exists for the order's quote the plugin must proceed normally.
     * This is the standard path for any non-Bold payment method.
     *
     * GAP 1 note: detection is done via the DB relation record, NOT via CheckPaymentMethod::isBold().
     * A non-Bold order simply has no row in bold_booster_magento_quote_bold_order.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testProceedsWhenNoBoldRelationRecordExists(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        // Use a quote ID that has no entry in bold_booster_magento_quote_bold_order
        $order->setQuoteId('99999998');
        $order->getPayment()->setMethod('checkmo');

        /** @var CheckoutSession|MockObject $session */
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getLastRealOrder')->willReturn($order);

        /** @var SuccessPlugin $plugin */
        $plugin = $objectManager->create(SuccessPlugin::class, ['checkoutSession' => $session]);

        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled): ResultInterface {
            $proceedCalled = true;
            return $this->createMock(ResultInterface::class);
        };

        $plugin->aroundExecute($this->createMock(Success::class), $proceed);

        self::assertTrue($proceedCalled, 'Expected $proceed to be called when no Bold relation record exists.');
    }

    /**
     * GAP 1 SCENARIO: order paid with bold_wallet but boldPaymentMethods DI is misconfigured
     * (bold_wallet omitted). isBold() returns false, BeforePlaceObserver skips auth.
     *
     * The plugin must STILL catch this because it checks the DB relation, not isBold().
     * If a relation record exists but authorized_at is null → redirect to cart.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testRedirectsWhenBoldRelationExistsButAuthTimestampIsNull(): void
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

        // No authorization happened — authorized_at is null
        $relation->setData('successful_auth_full_at', null);
        $resource->save($relation);

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        $order->setQuoteId($relation->getQuoteId());
        // bold_wallet would be the method that bypassed isBold() due to DI misconfiguration
        $order->getPayment()->setMethod('bold_wallet');

        /** @var CheckoutSession|MockObject $session */
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getLastRealOrder')->willReturn($order);

        /** @var SuccessPlugin $plugin */
        $plugin = $objectManager->create(SuccessPlugin::class, ['checkoutSession' => $session]);

        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled): ResultInterface {
            $proceedCalled = true;
            return $this->createMock(ResultInterface::class);
        };

        $result = $plugin->aroundExecute($this->createMock(Success::class), $proceed);

        self::assertFalse($proceedCalled, 'Expected $proceed NOT to be called — auth timestamp is missing.');
        self::assertInstanceOf(Redirect::class, $result, 'Expected a redirect response.');

        /** @var MessageManagerInterface $messageManager */
        $messageManager = $objectManager->get(MessageManagerInterface::class);
        $messages = $messageManager->getMessages(true)->getItems();
        self::assertNotEmpty($messages, 'Expected an error message to be added to the session.');
    }

    /**
     * Happy path: Bold relation record exists AND authorized_at is set → proceed to success page.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testProceedsWhenBoldRelationExistsAndAuthTimestampIsSet(): void
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

        // Authorization happened — authorized_at is set
        $relation->setData('successful_auth_full_at', '2026-01-01 00:00:00');
        $resource->save($relation);

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        $order->setQuoteId($relation->getQuoteId());
        $order->getPayment()->setMethod('bold');

        /** @var CheckoutSession|MockObject $session */
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getLastRealOrder')->willReturn($order);

        /** @var SuccessPlugin $plugin */
        $plugin = $objectManager->create(SuccessPlugin::class, ['checkoutSession' => $session]);

        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled): ResultInterface {
            $proceedCalled = true;
            return $this->createMock(ResultInterface::class);
        };

        $plugin->aroundExecute($this->createMock(Success::class), $proceed);

        self::assertTrue($proceedCalled, 'Expected $proceed to be called when authorized_at is set.');
    }

    /**
     * When the session has no last real order at all, the plugin must not interfere.
     */
    public function testProceedsWhenSessionHasNoLastRealOrder(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var CheckoutSession|MockObject $session */
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getLastRealOrder')->willReturn(
            $objectManager->create(Order::class) // empty order object, getId() returns null
        );

        /** @var SuccessPlugin $plugin */
        $plugin = $objectManager->create(SuccessPlugin::class, ['checkoutSession' => $session]);

        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled): ResultInterface {
            $proceedCalled = true;
            return $this->createMock(ResultInterface::class);
        };

        $plugin->aroundExecute($this->createMock(Success::class), $proceed);

        self::assertTrue($proceedCalled, 'Expected $proceed to be called when no order is in the session.');
    }

    /**
     * Cross-check: Bold auth_full_at IS set but NO Magento AUTH transaction exists.
     * This simulates a partial failure in saveTransactionData() — the Bold lifecycle table
     * was updated but addTransaction() never committed the row to sales_payment_transaction.
     * The plugin must redirect to cart.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testRedirectsWhenBoldAuthSetButNoMagentoTransaction(): void
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

        // Bold table says "authorized" …
        $relation->setData('successful_auth_full_at', '2026-01-01 00:00:00');
        $resource->save($relation);

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        $order->setQuoteId($relation->getQuoteId());
        $order->getPayment()->setMethod('bold');
        // … but no AUTH transaction row exists in sales_payment_transaction for this order

        /** @var CheckoutSession|MockObject $session */
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getLastRealOrder')->willReturn($order);

        /** @var SuccessPlugin $plugin */
        $plugin = $objectManager->create(SuccessPlugin::class, ['checkoutSession' => $session]);

        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled): ResultInterface {
            $proceedCalled = true;
            return $this->createMock(ResultInterface::class);
        };

        $result = $plugin->aroundExecute($this->createMock(Success::class), $proceed);

        self::assertFalse($proceedCalled, 'Expected $proceed NOT to be called — Magento transaction is missing.');
        self::assertInstanceOf(Redirect::class, $result, 'Expected a redirect response.');
    }

    /**
     * DUAL-CHECK happy path: Bold auth_full_at IS set AND Magento AUTH transaction exists.
     * Both signals agree — the guard must pass and call $proceed.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testProceedsWhenBothChecksPass(): void
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
        $order->getPayment()->setMethod('bold');

        // Create an AUTH transaction in sales_payment_transaction so Check 2 passes
        /** @var \Magento\Sales\Model\Order\Payment\Transaction $transaction */
        $transaction = $objectManager->create(\Magento\Sales\Model\Order\Payment\Transaction::class);
        $transaction->setOrderId((int) $order->getEntityId());
        $transaction->setPaymentId((int) $order->getPayment()->getEntityId());
        $transaction->setTxnId('test-auth-txn-success-page');
        $transaction->setTxnType(\Magento\Sales\Api\Data\TransactionInterface::TYPE_AUTH);
        $objectManager->get(\Magento\Sales\Api\TransactionRepositoryInterface::class)->save($transaction);

        /** @var CheckoutSession|MockObject $session */
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getLastRealOrder')->willReturn($order);

        /** @var SuccessPlugin $plugin */
        $plugin = $objectManager->create(SuccessPlugin::class, ['checkoutSession' => $session]);

        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled): ResultInterface {
            $proceedCalled = true;
            return $this->createMock(ResultInterface::class);
        };

        $plugin->aroundExecute($this->createMock(Success::class), $proceed);

        self::assertTrue($proceedCalled, 'Expected $proceed to be called when both checks pass.');
    }
}
