<?php

/**
 * Negative-scenario tests for SetCompleteState.
 *
 * SetCompleteState is the last gate in the Bold order-completion flow. It PUTs an
 * "order_complete" state to the Bold Checkout Sidekick API.  The two critical failure
 * modes tested here are:
 *
 *  1. The Bold public_order_id cannot be resolved → LocalizedException thrown before
 *     any API call is made.  This happens when AfterSubmitObserver runs after a cache
 *     flush or session loss: the extension-data row was never written (or was written
 *     without a public_id), so GetOrderPublicIdByOrderId cannot return a valid UUID.
 *
 *  2. The API call itself fails (non-201 response) → the method returns silently and
 *     logs an error; it must NOT throw, because the Magento order is already committed
 *     at this point and re-throwing would leave the customer in an error state.
 *
 * @magentoAppArea frontend
 */

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Model\Order;

use Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterface;
use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionData;
use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionDataFactory;
use Bold\CheckoutPaymentBooster\Model\Order\SetCompleteState;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\Order\OrderExtensionData as OrderExtensionDataResource;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class SetCompleteStateTest extends TestCase
{
    /**
     * When there is no bold_checkout_order_extension_data row for the order (i.e. the
     * public_order_id was never persisted), SetCompleteState must propagate the
     * LocalizedException thrown by GetOrderPublicIdByOrderId.
     *
     * This covers the "cache clean / session lost" scenario where the order was placed
     * but AfterSubmitObserver never had a public_order_id to write.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/order_simple.php
     */
    public function testThrowsWhenPublicOrderIdIsMissing(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        // Deliberately do NOT create an extension-data row so the lookup fails.
        /** @var SetCompleteState $service */
        $service = $objectManager->create(SetCompleteState::class);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/order public id is not set/i');

        $service->execute($order);
    }

    /**
     * When the Bold API returns a non-201 status, SetCompleteState must return silently
     * (logging the error) and must NOT throw.
     *
     * The Magento order is already persisted at this point, so throwing an exception
     * would create an inconsistent UX: the customer has paid but sees an error page.
     * The Bold retry mechanism handles the eventual reconciliation.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/order_simple.php
     * @magentoDbIsolation enabled
     */
    public function testDoesNotThrowWhenBoldApiReturnsFailureStatus(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        // Write a valid extension-data row so the public-id lookup succeeds.
        /** @var OrderExtensionDataFactory $factory */
        $factory = $objectManager->create(OrderExtensionDataFactory::class);
        /** @var OrderExtensionDataResource $resource */
        $resource = $objectManager->create(OrderExtensionDataResource::class);
        /** @var OrderExtensionData $extensionData */
        $extensionData = $factory->create();
        $extensionData->setOrderId((int) $order->getEntityId());
        $extensionData->setPublicId('b2c3d4e5-f6a7-8901-bcde-f12345678901');
        $resource->save($extensionData);

        // Mock the HTTP client to return a 500 status (API unavailable / Bold outage).
        /** @var ResultInterface|\PHPUnit\Framework\MockObject\MockObject $resultMock */
        $resultMock = $this->createMock(ResultInterface::class);
        $resultMock->method('getStatus')->willReturn(500);
        $resultMock->method('getErrors')->willReturn(['Internal server error']);

        /** @var BoldClient|\PHPUnit\Framework\MockObject\MockObject $clientMock */
        $clientMock = $this->createMock(BoldClient::class);
        $clientMock->method('put')->willReturn($resultMock);

        /** @var SetCompleteState $service */
        $service = $objectManager->create(SetCompleteState::class, ['client' => $clientMock]);

        // Must not throw — silent failure with logging is the correct behaviour.
        $service->execute($order);
        $this->addToAssertionCount(1); // reached here → no exception
    }

    /**
     * When the Bold API returns 201 (success), SetCompleteState must stamp the
     * successful_state_at timestamp on the quote relation record.
     *
     * This timestamp acts as the idempotency guard: AfterSubmitObserver will skip
     * re-processing any order where this timestamp is already set.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/order_simple.php
     * @magentoDbIsolation enabled
     */
    public function testStampsStateAtTimestampOnApiSuccess(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');

        // Load the Bold order relation that was created by the fixture.
        /** @var \Bold\CheckoutPaymentBooster\Model\MagentoQuoteBoldOrder $relation */
        $relation = $objectManager->create(\Bold\CheckoutPaymentBooster\Model\MagentoQuoteBoldOrder::class);
        /** @var \Bold\CheckoutPaymentBooster\Model\ResourceModel\MagentoQuoteBoldOrder $relResource */
        $relResource = $objectManager->create(\Bold\CheckoutPaymentBooster\Model\ResourceModel\MagentoQuoteBoldOrder::class);
        $relResource->load(
            $relation,
            'e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0',
            'bold_order_id'
        );

        // Write the extension-data row linking the Magento order to the Bold public_order_id.
        /** @var OrderExtensionDataFactory $factory */
        $factory = $objectManager->create(OrderExtensionDataFactory::class);
        /** @var OrderExtensionDataResource $resource */
        $resource = $objectManager->create(OrderExtensionDataResource::class);
        /** @var OrderExtensionData $extensionData */
        $extensionData = $factory->create();
        $extensionData->setOrderId((int) $order->getEntityId());
        $extensionData->setPublicId('e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0');
        $resource->save($extensionData);

        // Point the order at the fixture quote so saveStateAt can find the relation.
        $order->setQuoteId($relation->getQuoteId());

        // Mock the API to return success.
        /** @var ResultInterface|\PHPUnit\Framework\MockObject\MockObject $resultMock */
        $resultMock = $this->createMock(ResultInterface::class);
        $resultMock->method('getStatus')->willReturn(201);

        /** @var BoldClient|\PHPUnit\Framework\MockObject\MockObject $clientMock */
        $clientMock = $this->createMock(BoldClient::class);
        $clientMock->method('put')->willReturn($resultMock);

        /** @var SetCompleteState $service */
        $service = $objectManager->create(SetCompleteState::class, ['client' => $clientMock]);
        $service->execute($order);

        // Reload the relation and assert the timestamp was written.
        $relResource->load(
            $relation,
            'e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0',
            'bold_order_id'
        );
        self::assertNotNull(
            $relation->getData('successful_state_at'),
            'successful_state_at must be stamped after a successful SetCompleteState call'
        );
    }
}
