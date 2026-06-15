<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Model\Order;

use Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterface;
use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Bold\CheckoutPaymentBooster\Model\Order\HydrateOrderFromQuote;
use Bold\CheckoutPaymentBooster\Test\Integration\_Support\IntegrationTestCase;
use Bold\CheckoutPaymentBooster\Test\Integration\_Support\NonBaseCurrencyQuoteTrait;
use Magento\TestFramework\Helper\Bootstrap;

class HydrateOrderFromQuoteTest extends IntegrationTestCase
{
    use NonBaseCurrencyQuoteTrait;

    /**
     * @dataProvider nonBaseDisplayCurrencyProvider
     * @magentoDataFixture Magento/SalesRule/_files/cart_rule_with_coupon_5_off_no_condition.php
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     * @magentoDbIsolation enabled
     */
    public function testHydrateSendsBaseCurrencyAmountsForNonBaseCurrencyQuote(string $displayCurrency): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $quote = $this->prepareQuoteWithDisplayCurrency('test_order_1', $displayCurrency);
        $this->assertQuoteUsesNonBaseCurrency($quote, $displayCurrency);
        $this->assertDisplayAndBaseGrandTotalsDiffer($quote);

        $capturedBody = null;
        $boldApiResultMock = $this->createMock(ResultInterface::class);
        $boldApiResultMock->method('getStatus')->willReturn(201);

        $boldClientMock = $this->createMock(BoldClient::class);
        $boldClientMock->method('put')->willReturnCallback(
            static function (int $websiteId, string $url, array $body) use ($boldApiResultMock, &$capturedBody) {
                $capturedBody = $body;

                return $boldApiResultMock;
            }
        );

        $repositoryMock = $this->createMock(MagentoQuoteBoldOrderRepositoryInterface::class);
        $repositoryMock->expects(self::once())->method('saveHydratedAt');

        $hydrateOrderFromQuote = $objectManager->create(
            HydrateOrderFromQuote::class,
            [
                'client' => $boldClientMock,
                'magentoQuoteBoldOrderRepository' => $repositoryMock,
            ]
        );

        $hydrateOrderFromQuote->hydrate($quote, 'test-public-order-id');

        self::assertIsArray($capturedBody);
        self::assertArrayHasKey('totals', $capturedBody);

        $expectedOrderTotal = (int) round((float) $quote->getBaseGrandTotal() * 100);
        $expectedSubTotal = (int) round((float) $quote->getBaseSubtotal() * 100);
        $expectedTaxTotal = (int) round((float) ($quote->getShippingAddress()->getBaseTaxAmount() ?? 0) * 100);
        $expectedShippingTotal = (int) round((float) $quote->getShippingAddress()->getBaseShippingAmount() * 100);

        self::assertSame($expectedOrderTotal, $capturedBody['totals']['order_total']);
        self::assertSame($expectedSubTotal, $capturedBody['totals']['sub_total']);
        self::assertSame($expectedTaxTotal, $capturedBody['totals']['tax_total']);
        self::assertSame($expectedShippingTotal, $capturedBody['totals']['shipping_total']);
        self::assertNotSame(
            (int) round((float) $quote->getGrandTotal() * 100),
            $capturedBody['totals']['order_total'],
            'Hydration must not send display-currency grand total when quote currency differs from base'
        );
    }
}
