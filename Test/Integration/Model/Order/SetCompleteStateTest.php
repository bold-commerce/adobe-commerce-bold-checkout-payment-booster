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
 *   1. bold_booster_magento_quote_bold_order relation record must exist
 *   2. bold_booster_magento_quote_bold_order.successful_auth_full_at must be non-null (Bold lifecycle table)
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
     * When BOTH checks pass (relation record exists AND bold_auth_full_at is set),
     * the guard passes and execution reaches the API-call phase.
     *
     * A LocalizedException is acceptable here:
     *   "Order public id is not set." — thrown by GetOrderPublicIdByOrderId because the
     *   order.php fixture has no Bold OrderExtensionData record. This confirms both
     *   guards passed and execution moved on to resolving the Bold public order ID.
     *
     * The test fails only if a guard exception is thrown (i.e. authorization was rejected).
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testBothChecksPassWhenRelationExistsAndAuthTimestampSet(): void
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

        /** @var SetCompleteState $setCompleteState */
        $setCompleteState = $objectManager->create(SetCompleteState::class);

        try {
            $setCompleteState->execute($order);
        } catch (LocalizedException $e) {
            $guardMessages = [
                'payment authorization has not been recorded',
                'no Bold order relation record found',
            ];
            foreach ($guardMessages as $guardMsg) {
                self::assertStringNotContainsStringIgnoringCase(
                    $guardMsg,
                    $e->getMessage(),
                    sprintf('Guard should NOT have thrown for: %s', $guardMsg)
                );
            }

            // After the guards, GetOrderPublicIdByOrderId throws because the order.php
            // fixture has no Bold OrderExtensionData record. This is the expected
            // terminal exception and confirms execution reached the API-call phase.
            self::assertStringContainsStringIgnoringCase(
                'Order public id is not set',
                $e->getMessage(),
                'Expected execution to reach the API-call phase and fail on missing public order ID'
            );
        }
    }
}
