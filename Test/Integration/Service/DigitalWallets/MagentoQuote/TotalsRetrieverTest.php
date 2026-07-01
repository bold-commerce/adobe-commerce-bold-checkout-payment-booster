<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Service\DigitalWallets\MagentoQuote;

use Bold\CheckoutPaymentBooster\Test\Integration\_Support\IntegrationTestCase;
use Bold\CheckoutPaymentBooster\Test\Integration\_Support\NonBaseCurrencyQuoteTrait;
use Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote\TotalsRetriever;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\TestFramework\Helper\Bootstrap;

use function count;

class TotalsRetrieverTest extends IntegrationTestCase
{
    use NonBaseCurrencyQuoteTrait;

    private const NON_EXISTENT_QUOTE_ID = 999999999;

    /**
     * @magentoDataFixture Magento/Checkout/_files/quote_with_items_saved.php
     */
    public function testRetrievesQuoteItemTotalsSuccessfully(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var QuoteResourceModel $quoteResourceModel */
        $quoteResourceModel = $objectManager->create(QuoteResourceModel::class);
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var TotalsRetriever $totalsRetriever */
        $totalsRetriever = $objectManager->create(TotalsRetriever::class);

        $quoteResourceModel->load($quote, 'test_order_item_with_items', 'reserved_order_id');

        $expectedTotals = [
            'subtotal' => $quote->getSubtotal(),
            'base_subtotal' => $quote->getBaseSubtotal(),
            'grand_total' => $quote->getGrandTotal(),
            'base_grand_total' => $quote->getBaseGrandTotal(),
        ];
        $actualTotals = $totalsRetriever->retrieveTotals($quote->getId());

        self::assertCount((int)$quote->getItemsCount(), $actualTotals['items']);
        self::assertCount(count($quote->getTotals()), $actualTotals['total_segments']);
        foreach ($expectedTotals as $key => $expectedValue) {
            self::assertArrayHasKey($key, $actualTotals);
            self::assertEquals($expectedValue, $actualTotals[$key], "Totals key '{$key}' mismatch.");
        }
    }

    /**
     * @dataProvider nonBaseDisplayCurrencyProvider
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Checkout/_files/quote_with_items_saved.php
     */
    public function testRetrievesQuoteItemTotalsWithNonBaseCurrencyQuote(string $displayCurrency): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $quote = $this->prepareQuoteWithDisplayCurrency('test_order_item_with_items', $displayCurrency, true);
        $totalsRetriever = $objectManager->create(TotalsRetriever::class);

        $this->assertQuoteUsesNonBaseCurrency($quote, $displayCurrency);
        $this->assertDisplayAndBaseGrandTotalsDiffer($quote);

        $actualTotals = $totalsRetriever->retrieveTotals($quote->getId());

        self::assertEquals($quote->getGrandTotal(), $actualTotals['grand_total']);
        self::assertEquals($quote->getBaseGrandTotal(), $actualTotals['base_grand_total']);
        self::assertNotEquals($actualTotals['grand_total'], $actualTotals['base_grand_total']);
    }

    public function testThrowsExceptionForInvalidQuoteId(): void
    {
        $this->expectExceptionMessage('No such entity with cartId = ' . self::NON_EXISTENT_QUOTE_ID);

        $objectManager = Bootstrap::getObjectManager();
        /** @var TotalsRetriever $totalsRetriever */
        $totalsRetriever = $objectManager->create(TotalsRetriever::class);

        $totalsRetriever->retrieveTotals(self::NON_EXISTENT_QUOTE_ID);
    }
}
