<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Model;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Eps\GetFastlaneStyles;
use Bold\CheckoutPaymentBooster\Model\InitOrderFromQuote;
use Bold\CheckoutPaymentBooster\Model\ResumeOrder;
use Magento\Checkout\Model\Session;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class CheckoutDataTest extends TestCase
{
    private const EXISTING_PUBLIC_ORDER_ID = 'e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0';

    private const NEW_PUBLIC_ORDER_ID = 'aca5efca525f4748be5820d62d95c88b2e9b11b98bb643fc93b2109500a2f993';

    /**
     * @magentoConfigFixture current_website checkout/bold_checkout_payment_booster/shop_id test-shop-id
     * @magentoConfigFixture current_website checkout/bold_checkout_payment_booster/is_payment_booster_enabled 1
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     */
    public function testReplacesStalePublicOrderIdWhenQuoteAlreadyProcessed(): void
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

        $quoteResource->load($quote, 'test_order_item_with_items', 'reserved_order_id');
        $quoteId = (string)$quote->getId();

        $magentoQuoteBoldOrderRepository->saveStateAt($quoteId);

        $checkoutSession->replaceQuote($quote);
        $checkoutSession->setBoldCheckoutData(
            [
                'data' => [
                    'public_order_id' => self::EXISTING_PUBLIC_ORDER_ID,
                    'jwt_token' => 'stale-jwt-token',
                ],
            ]
        );

        /** @var ResumeOrder&MockObject $resumeOrder */
        $resumeOrder = $this->createMock(ResumeOrder::class);
        $resumeOrder
            ->expects(self::never())
            ->method('resume')
            ->with(self::EXISTING_PUBLIC_ORDER_ID);

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

        self::assertNotSame(self::EXISTING_PUBLIC_ORDER_ID, $checkoutData->getPublicOrderId());
        self::assertSame(self::NEW_PUBLIC_ORDER_ID, $checkoutData->getPublicOrderId());
        self::assertSame('new-jwt-token', $checkoutData->getJwtToken());
    }

    /**
     * @magentoConfigFixture current_website checkout/bold_checkout_payment_booster/shop_id test-shop-id
     * @magentoConfigFixture current_website checkout/bold_checkout_payment_booster/is_payment_booster_enabled 1
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     */
    public function testInitCheckoutDataResumesWhenQuoteNotProcessed(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var Session $checkoutSession */
        $checkoutSession = $objectManager->get(Session::class);

        $quoteResource->load($quote, 'test_order_item_with_items', 'reserved_order_id');
        $websiteId = (int)$quote->getStore()->getWebsiteId();

        $checkoutSession->replaceQuote($quote);
        $checkoutSession->setBoldCheckoutData(
            [
                'data' => [
                    'public_order_id' => self::EXISTING_PUBLIC_ORDER_ID,
                    'jwt_token' => 'existing-jwt-token',
                ],
            ]
        );

        /** @var ResumeOrder&MockObject $resumeOrder */
        $resumeOrder = $this->createMock(ResumeOrder::class);
        $resumeOrder
            ->expects(self::once())
            ->method('resume')
            ->with(self::EXISTING_PUBLIC_ORDER_ID, $websiteId)
            ->willReturn(
                [
                    'data' => [
                        'public_order_id' => self::EXISTING_PUBLIC_ORDER_ID,
                        'jwt_token' => 'resumed-jwt-token',
                    ],
                ]
            );

        /** @var InitOrderFromQuote&MockObject $initOrderFromQuote */
        $initOrderFromQuote = $this->createMock(InitOrderFromQuote::class);
        $initOrderFromQuote->expects(self::never())->method('init');

        $this->registerCheckoutDataDependencies($objectManager, $resumeOrder, $initOrderFromQuote);

        /** @var CheckoutData $checkoutData */
        $checkoutData = $objectManager->create(CheckoutData::class);
        $checkoutData->initCheckoutData();

        self::assertSame(self::EXISTING_PUBLIC_ORDER_ID, $checkoutData->getPublicOrderId());
        self::assertSame('resumed-jwt-token', $checkoutData->getJwtToken());
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
