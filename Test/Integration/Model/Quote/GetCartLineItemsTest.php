<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Model\Quote;

use Bold\CheckoutPaymentBooster\Model\Quote\GetCartLineItems;
use Bold\CheckoutPaymentBooster\Test\Integration\_Support\IntegrationTestCase;
use Bold\CheckoutPaymentBooster\Test\Integration\_Support\NonBaseCurrencyQuoteTrait;
use Magento\Quote\Model\Quote\Item;
use Magento\TestFramework\Helper\Bootstrap;

class GetCartLineItemsTest extends IntegrationTestCase
{
    use NonBaseCurrencyQuoteTrait;

    /**
     * @dataProvider nonBaseDisplayCurrencyProvider
     * @magentoDataFixture Magento/SalesRule/_files/cart_rule_with_coupon_5_off_no_condition.php
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     * @magentoDbIsolation enabled
     */
    public function testGetItemsUsesBasePricesForNonBaseCurrencyQuote(string $displayCurrency): void
    {
        $quote = $this->prepareQuoteWithDisplayCurrency('test_order_1', $displayCurrency);
        $this->assertQuoteUsesNonBaseCurrency($quote, $displayCurrency);
        $this->assertDisplayAndBaseGrandTotalsDiffer($quote);

        $getCartLineItems = Bootstrap::getObjectManager()->get(GetCartLineItems::class);
        $lineItems = $getCartLineItems->getItems($quote);

        self::assertNotEmpty($lineItems);

        /** @var Item $quoteItem */
        $quoteItem = $quote->getAllVisibleItems()[0];
        $expectedPriceCents = (int) round((float) $quoteItem->getBasePrice() * 100);

        self::assertSame($expectedPriceCents, $lineItems[0]['price']);
    }
}
