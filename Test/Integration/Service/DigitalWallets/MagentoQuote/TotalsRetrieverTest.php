<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Service\DigitalWallets\MagentoQuote;

use Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote\TotalsRetriever;
use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function count;

class TotalsRetrieverTest extends TestCase
{
    use ArraySubsetAsserts;

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
        self::assertArraySubset($expectedTotals, $actualTotals);
    }

    public function testThrowsExceptionForInvalidQuoteId(): void
    {
        $this->expectExceptionMessage('No such entity with cartId = 42');

        $objectManager = Bootstrap::getObjectManager();
        /** @var TotalsRetriever $totalsRetriever */
        $totalsRetriever = $objectManager->create(TotalsRetriever::class);

        $totalsRetriever->retrieveTotals(42);
    }
}
