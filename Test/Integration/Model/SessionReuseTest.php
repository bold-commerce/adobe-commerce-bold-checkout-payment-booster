<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Model;

use Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterface;
use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Eps\GetFastlaneStyles;
use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Bold\CheckoutPaymentBooster\Model\InitOrderFromQuote;
use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionData;
use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionDataFactory;
use Bold\CheckoutPaymentBooster\Model\Order\SetCompleteState;
use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Bold\CheckoutPaymentBooster\Model\ResumeOrder;
use Magento\Checkout\Model\Session;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for CHK-9605: reusing the same public_order_id on a completed DW quote.
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class SessionReuseTest extends TestCase
{
    private const STALE_PUBLIC_ORDER_ID = 'e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0';

    private const NEW_PUBLIC_ORDER_ID = 'aca5efca525f4748be5820d62d95c88b2e9b11b98bb643fc93b2109500a2f993';

    /**
     * Ozeparts smoking-gun path: order #1 completed, same quote reused, stale session ID must not resume.
     *
     * @magentoConfigFixture current_website checkout/bold_checkout_payment_booster/shop_id test-shop-id
     * @magentoConfigFixture current_website checkout/bold_checkout_payment_booster/is_payment_booster_enabled 1
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     */
    public function testSecondCheckoutReplacesStalePublicOrderIdOnProcessedQuote(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var Session $checkoutSession */
        $checkoutSession = $objectManager->get(Session::class);
        /** @var MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository */
        $magentoQuoteBoldOrderRepository = $objectManager->create(MagentoQuoteBoldOrderRepositoryInterface::class);
        /** @var CartRepositoryInterface $cartRepository */
        $cartRepository = $objectManager->create(CartRepositoryInterface::class);

        $quoteResource->load($quote, 'test_order_item_with_items', 'reserved_order_id');
        $quoteId = (string)$quote->getId();

        // Simulate order #1 order_complete: quote marked processed, bold_order_id cleared.
        $magentoQuoteBoldOrderRepository->saveStateAt($quoteId);
        $relation = $magentoQuoteBoldOrderRepository->getByQuoteId($quoteId);
        $relation->setBoldOrderId('');
        $magentoQuoteBoldOrderRepository->save($relation);

        self::assertTrue($magentoQuoteBoldOrderRepository->isQuoteProcessed($quoteId));

        // Stale Bold session from order #1 (the bug would resume this ID).
        $checkoutSession->replaceQuote($quote);
        $checkoutSession->setBoldCheckoutData(
            [
                'data' => [
                    'public_order_id' => self::STALE_PUBLIC_ORDER_ID,
                    'jwt_token' => 'stale-jwt-token',
                ],
            ]
        );

        /** @var ResumeOrder&MockObject $resumeOrder */
        $resumeOrder = $this->createMock(ResumeOrder::class);
        $resumeOrder
            ->expects(self::never())
            ->method('resume')
            ->with(self::STALE_PUBLIC_ORDER_ID);

        /** @var InitOrderFromQuote&MockObject $initOrderFromQuote */
        $initOrderFromQuote = $this->createMock(InitOrderFromQuote::class);
        $initOrderFromQuote
            ->expects(self::once())
            ->method('init')
            ->with(self::identicalTo($quote))
            ->willReturn(
                [
                    'data' => [
                        'public_order_id' => self::NEW_PUBLIC_ORDER_ID,
                        'jwt_token' => 'new-jwt-token',
                        'flow_settings' => [],
                    ],
                ]
            );

        $this->registerCheckoutDataDependencies($objectManager, $resumeOrder, $initOrderFromQuote);

        /** @var CheckoutData $checkoutData */
        $checkoutData = $objectManager->create(CheckoutData::class);
        $checkoutData->initCheckoutData();

        self::assertNotSame(self::STALE_PUBLIC_ORDER_ID, $checkoutData->getPublicOrderId());
        self::assertSame(self::NEW_PUBLIC_ORDER_ID, $checkoutData->getPublicOrderId());

        $reloadedQuote = $cartRepository->get((int)$quoteId);
        self::assertNull($reloadedQuote->getExtensionAttributes()->getBoldOrderId());
    }

    /**
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     * @magentoDataFixture Magento/Sales/_files/order.php
     */
    public function testOrderCompleteClearsBoldOrderIdOnQuoteRelation(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var Order $order */
        $order = $objectManager->create(Order::class);
        /** @var OrderResource $orderResource */
        $orderResource = $objectManager->create(OrderResource::class);
        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = $objectManager->get(OrderRepositoryInterface::class);
        /** @var OrderExtensionDataFactory $orderExtensionDataFactory */
        $orderExtensionDataFactory = $objectManager->get(OrderExtensionDataFactory::class);
        /** @var OrderExtensionDataRepository $orderExtensionDataRepository */
        $orderExtensionDataRepository = $objectManager->get(OrderExtensionDataRepository::class);
        /** @var MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository */
        $magentoQuoteBoldOrderRepository = $objectManager->create(MagentoQuoteBoldOrderRepositoryInterface::class);

        $quoteResource->load($quote, 'test_order_item_with_items', 'reserved_order_id');
        $quoteId = (string)$quote->getId();

        $orderResource->load($order, '100000001', 'increment_id');
        $order->setQuoteId($quote->getId());
        $orderRepository->save($order);

        /** @var OrderExtensionData $orderExtensionData */
        $orderExtensionData = $orderExtensionDataFactory->create();
        $orderExtensionData->setOrderId((int)$order->getEntityId());
        $orderExtensionData->setPublicId(self::STALE_PUBLIC_ORDER_ID);
        $orderExtensionDataRepository->save($orderExtensionData);

        $boldApiResultMock = $this->createMock(ResultInterface::class);
        $boldApiResultMock->method('getStatus')->willReturn(201);

        /** @var BoldClient&MockObject $boldClientMock */
        $boldClientMock = $this->createMock(BoldClient::class);
        $boldClientMock
            ->expects(self::once())
            ->method('put')
            ->willReturn($boldApiResultMock);

        /** @var SetCompleteState $setCompleteState */
        $setCompleteState = $objectManager->create(
            SetCompleteState::class,
            [
                'client' => $boldClientMock,
            ]
        );
        $setCompleteState->execute($order);

        $relation = $magentoQuoteBoldOrderRepository->getByQuoteId($quoteId);
        self::assertTrue($relation->isProcessed());
        self::assertSame('', $relation->getBoldOrderId());
        self::assertNotSame(self::STALE_PUBLIC_ORDER_ID, $relation->getBoldOrderId());
    }

    /**
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address.php
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/digital_wallets_quote.php
     */
    public function testDigitalWalletQuotePreservationClearsStaleBoldSessionData(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $regularQuote */
        $regularQuote = $objectManager->create(Quote::class);
        /** @var Quote $digitalWalletsQuote */
        $digitalWalletsQuote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var Session $checkoutSession */
        $checkoutSession = $objectManager->get(Session::class);

        $quoteResource->load($regularQuote, 'test_order_1', 'reserved_order_id');
        $quoteResource->load($digitalWalletsQuote, 'digital_wallets_order_1', 'reserved_order_id');

        $checkoutSession->replaceQuote($regularQuote);
        $checkoutSession->setLastQuoteId($digitalWalletsQuote->getId());
        $checkoutSession->setBoldCheckoutData(
            [
                'data' => [
                    'public_order_id' => self::STALE_PUBLIC_ORDER_ID,
                    'jwt_token' => 'stale-jwt-token',
                ],
            ]
        );

        $checkoutSession->clearQuote();

        self::assertTrue($checkoutSession->hasQuote());
        self::assertNull($checkoutSession->getBoldCheckoutData());
    }

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param ResumeOrder&MockObject $resumeOrder
     * @param InitOrderFromQuote&MockObject $initOrderFromQuote
     */
    private function registerCheckoutDataDependencies(
        $objectManager,
        ResumeOrder $resumeOrder,
        InitOrderFromQuote $initOrderFromQuote
    ): void {
        $getFastlaneStylesStub = $this->createStub(GetFastlaneStyles::class);
        $getFastlaneStylesStub
            ->method('getStyles')
            ->willReturn([]);

        $objectManager->configure(
            [
                ResumeOrder::class => [
                    'shared' => true,
                ],
                InitOrderFromQuote::class => [
                    'shared' => true,
                ],
                GetFastlaneStyles::class => [
                    'shared' => true,
                ],
            ]
        );
        $objectManager->addSharedInstance($resumeOrder, ResumeOrder::class);
        $objectManager->addSharedInstance($initOrderFromQuote, InitOrderFromQuote::class);
        $objectManager->addSharedInstance($getFastlaneStylesStub, GetFastlaneStyles::class);
    }
}
