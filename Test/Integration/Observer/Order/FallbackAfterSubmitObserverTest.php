<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Observer\Order;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\Order\CheckPaymentMethod;
use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionDataFactory;
use Bold\CheckoutPaymentBooster\Model\Order\SetCompleteState;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\Order\OrderExtensionData as OrderExtensionDataResource;
use Bold\CheckoutPaymentBooster\Observer\Order\FallbackAfterSubmitObserver;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Integration tests for FallbackAfterSubmitObserver.
 *
 * FallbackAfterSubmitObserver extends AfterSubmitObserver and gates execution on
 * Config::useFallbackObserver(). It is bound to sales_order_save_after and
 * sales_order_save_commit_after events as a backup path for environments where
 * checkout_submit_all_after is not dispatched reliably.
 *
 * Covers:
 *  - No-op when useFallbackObserver config is false
 *  - Parent logic runs when useFallbackObserver config is true
 *  - LocalizedException from parent is caught + logged (not rethrown)
 *  - NoSuchEntityException from parent is silently swallowed
 *
 * @magentoAppArea frontend
 */
class FallbackAfterSubmitObserverTest extends TestCase
{
    private function buildObserver(array $overrides = []): FallbackAfterSubmitObserver
    {
        return Bootstrap::getObjectManager()->create(FallbackAfterSubmitObserver::class, $overrides);
    }

    private function buildObserverEvent(?Order $order): Observer
    {
        $event = new Event(['order' => $order]);
        $observer = new Observer();
        $observer->setEvent($event);
        return $observer;
    }

    /**
     * When useFallbackObserver config is false the parent execute() must be bypassed
     * entirely — SetCompleteState must never be reached.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testDoesNothingWhenFallbackObserverConfigIsDisabled(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        $order->getPayment()->setMethod('bold');

        /** @var Config|MockObject $config */
        $config = $this->createMock(Config::class);
        $config->method('useFallbackObserver')->willReturn(false);

        /** @var SetCompleteState|MockObject $setCompleteState */
        $setCompleteState = $this->createMock(SetCompleteState::class);
        $setCompleteState->expects(self::never())->method('execute');

        $observer = $this->buildObserver([
            'config'          => $config,
            'setCompleteState' => $setCompleteState,
        ]);

        // Must not throw and must not touch SetCompleteState
        $observer->execute($this->buildObserverEvent($order));
    }

    /**
     * When useFallbackObserver config is true and the order was already processed
     * (isBoldOrderProcessed returns true), the parent's early-exit guard runs and
     * SetCompleteState is still never called.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testSkipsAlreadyProcessedOrderEvenWhenFallbackEnabled(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        $order->getPayment()->setMethod('bold');

        /** @var Config|MockObject $config */
        $config = $this->createMock(Config::class);
        $config->method('useFallbackObserver')->willReturn(true);

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
            'config'                         => $config,
            'checkPaymentMethod'             => $checkPaymentMethod,
            'magentoQuoteBoldOrderRepository' => $repo,
            'setCompleteState'               => $setCompleteState,
        ]);

        $observer->execute($this->buildObserverEvent($order));
    }

    /**
     * When useFallbackObserver config is true and SetCompleteState throws a
     * LocalizedException (e.g. the auth guard fired), the observer must catch it,
     * log it at critical, and NOT rethrow — the order is already committed.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testCatchesAndLogsLocalizedExceptionFromParent(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        $order->getPayment()->setMethod('bold');

        /** @var Config|MockObject $config */
        $config = $this->createMock(Config::class);
        $config->method('useFallbackObserver')->willReturn(true);

        /** @var CheckPaymentMethod|MockObject $checkPaymentMethod */
        $checkPaymentMethod = $this->createMock(CheckPaymentMethod::class);
        $checkPaymentMethod->method('isBold')->willReturn(true);

        /** @var MagentoQuoteBoldOrderRepositoryInterface|MockObject $repo */
        $repo = $this->createMock(MagentoQuoteBoldOrderRepositoryInterface::class);
        $repo->method('isBoldOrderProcessed')->willReturn(false);
        $repo->method('getPublicOrderIdFromOrder')->willReturn('test-public-order-id');

        /** @var CheckoutData|MockObject $checkoutData */
        $checkoutData = $this->createMock(CheckoutData::class);
        $checkoutData->method('getPublicOrderId')->willReturn(null);

        /** @var OrderExtensionDataResource|MockObject $extensionDataResource */
        $extensionDataResource = $this->createMock(OrderExtensionDataResource::class);

        /** @var SetCompleteState|MockObject $setCompleteState */
        $setCompleteState = $this->createMock(SetCompleteState::class);
        $setCompleteState->method('execute')
            ->willThrowException(new LocalizedException(__('Auth guard: no Bold lifecycle auth record')));

        /** @var LoggerInterface|MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())->method('critical');

        $observer = $this->buildObserver([
            'config'                         => $config,
            'checkPaymentMethod'             => $checkPaymentMethod,
            'magentoQuoteBoldOrderRepository' => $repo,
            'checkoutData'                   => $checkoutData,
            'orderExtensionDataResource'     => $extensionDataResource,
            'setCompleteState'               => $setCompleteState,
            'logger'                         => $logger,
        ]);

        // Must NOT throw — exception is silently caught by the fallback observer
        $observer->execute($this->buildObserverEvent($order));
    }

    /**
     * When useFallbackObserver config is true and the parent throws a NoSuchEntityException
     * (e.g. the quote relation lookup fails), the observer must swallow it silently.
     *
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testSwallowsNoSuchEntityExceptionFromParent(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        $order->loadByIncrementId('100000001');
        $order->getPayment()->setMethod('bold');

        /** @var Config|MockObject $config */
        $config = $this->createMock(Config::class);
        $config->method('useFallbackObserver')->willReturn(true);

        /** @var CheckPaymentMethod|MockObject $checkPaymentMethod */
        $checkPaymentMethod = $this->createMock(CheckPaymentMethod::class);
        $checkPaymentMethod->method('isBold')->willReturn(true);

        /** @var MagentoQuoteBoldOrderRepositoryInterface|MockObject $repo */
        $repo = $this->createMock(MagentoQuoteBoldOrderRepositoryInterface::class);
        // isBoldOrderProcessed itself throws NoSuchEntityException to simulate a missing record
        $repo->method('isBoldOrderProcessed')
            ->willThrowException(new NoSuchEntityException(__('Quote relation not found')));

        $observer = $this->buildObserver([
            'config'                         => $config,
            'checkPaymentMethod'             => $checkPaymentMethod,
            'magentoQuoteBoldOrderRepository' => $repo,
        ]);

        // Must NOT throw
        $observer->execute($this->buildObserverEvent($order));
    }

    /**
     * Sanity check: when the order is null, the observer must return cleanly
     * regardless of the useFallbackObserver config value.
     */
    public function testHandlesNullOrderGracefully(): void
    {
        /** @var Config|MockObject $config */
        $config = $this->createMock(Config::class);
        $config->method('useFallbackObserver')->willReturn(true);

        /** @var SetCompleteState|MockObject $setCompleteState */
        $setCompleteState = $this->createMock(SetCompleteState::class);
        $setCompleteState->expects(self::never())->method('execute');

        $observer = $this->buildObserver([
            'config'          => $config,
            'setCompleteState' => $setCompleteState,
        ]);

        // Must not throw when the event carries a null order
        $observer->execute($this->buildObserverEvent(null));
    }
}
