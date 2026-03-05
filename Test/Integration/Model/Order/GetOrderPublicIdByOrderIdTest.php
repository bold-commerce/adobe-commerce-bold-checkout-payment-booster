<?php

/**
 * Negative-scenario tests for GetOrderPublicIdByOrderId.
 *
 * Covers the "missing public_order_id" failure modes that arise in real flows when:
 *  - The order-extension-data table has no record for the given order_id (e.g. after a
 *    cache flush, a failed AfterSubmitObserver run, or a direct DB operation that left the
 *    table in an inconsistent state).
 *  - The record exists but the public_id column was never populated (AfterSubmitObserver
 *    saved a skeleton record when it could not resolve a public_order_id from session or DB).
 */

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Model\Order;

use Bold\CheckoutPaymentBooster\Model\Order\GetOrderPublicIdByOrderId;
use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionData;
use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionDataFactory;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\Order\OrderExtensionData as OrderExtensionDataResource;
use Magento\Framework\Exception\LocalizedException;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea frontend
 */
class GetOrderPublicIdByOrderIdTest extends TestCase
{
    /**
     * When the bold_checkout_order_extension_data table has no row for the given order_id,
     * execute() must throw a LocalizedException.
     *
     * This mirrors the "cache clean" scenario: the session public_order_id was cleared and the
     * AfterSubmitObserver never wrote a row because the flow failed at an earlier step.
     */
    public function testThrowsWhenNoExtensionDataRecordExistsForOrder(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var GetOrderPublicIdByOrderId $service */
        $service = $objectManager->create(GetOrderPublicIdByOrderId::class);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/order public id is not set/i');

        $service->execute(999999); // no row in the extension-data table for this id
    }

    /**
     * When the extension-data row exists but was saved without a public_id (AfterSubmitObserver
     * creates a skeleton row even when it cannot resolve the Bold public_order_id), execute()
     * must throw a LocalizedException instead of returning null and triggering a TypeError.
     *
     * This is the "missing public_order_id in the middle of the flow" scenario: the Magento order
     * was persisted and the skeleton extension-data row was written, but the Bold order relation
     * was never established (e.g. customer navigated away before checkout completed, or the
     * CheckoutData session was lost after a server restart).
     *
     * @magentoDbIsolation enabled
     */
    public function testThrowsWhenExtensionDataExistsButPublicIdIsNull(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var OrderExtensionDataFactory $factory */
        $factory = $objectManager->create(OrderExtensionDataFactory::class);
        /** @var OrderExtensionDataResource $resource */
        $resource = $objectManager->create(OrderExtensionDataResource::class);

        /** @var OrderExtensionData $extensionData */
        $extensionData = $factory->create();
        $extensionData->setOrderId(888888); // intentionally no setPublicId()
        $resource->save($extensionData);

        /** @var GetOrderPublicIdByOrderId $service */
        $service = $objectManager->create(GetOrderPublicIdByOrderId::class);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/order public id is not set/i');

        $service->execute(888888);
    }

    /**
     * Happy path: when the row exists and has a valid public_id, execute() returns it.
     *
     * @magentoDbIsolation enabled
     */
    public function testReturnsPublicIdWhenExtensionDataRecordIsComplete(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $expectedPublicId = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

        /** @var OrderExtensionDataFactory $factory */
        $factory = $objectManager->create(OrderExtensionDataFactory::class);
        /** @var OrderExtensionDataResource $resource */
        $resource = $objectManager->create(OrderExtensionDataResource::class);

        /** @var OrderExtensionData $extensionData */
        $extensionData = $factory->create();
        $extensionData->setOrderId(777777);
        $extensionData->setPublicId($expectedPublicId);
        $resource->save($extensionData);

        /** @var GetOrderPublicIdByOrderId $service */
        $service = $objectManager->create(GetOrderPublicIdByOrderId::class);

        self::assertSame($expectedPublicId, $service->execute(777777));
    }
}
