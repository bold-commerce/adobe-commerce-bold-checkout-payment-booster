<?php

declare(strict_types=1);

use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;

require __DIR__ . '/quote_with_shipping_tax_and_discount.php';

$objectManager = Bootstrap::getObjectManager();
/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);
/** @var QuoteResource $quoteResource */
$quoteResource = $objectManager->get(QuoteResource::class);
$quote = $quoteFactory->create();
// quote_with_shipping_tax_and_discount creates quote with reserved_order_id 'test_order_1'
$quoteResource->load($quote, 'test_order_1', 'reserved_order_id');
if (!$quote->getId()) {
    throw new \RuntimeException('Quote with reserved_order_id test_order_1 not found.');
}
$store = $quote->getStore();
$store->unsetData('current_currency');
$store->setCurrentCurrencyCode('EUR');
$quote->setBaseCurrencyCode('USD');
$quote->setQuoteCurrencyCode('EUR');
$quote->collectTotals();
$quote->save();
