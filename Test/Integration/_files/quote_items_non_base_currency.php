<?php

declare(strict_types=1);

use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Checkout/_files/quote_with_items_saved.php');

$objectManager = Bootstrap::getObjectManager();
/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);
/** @var QuoteResource $quoteResource */
$quoteResource = $objectManager->get(QuoteResource::class);
$quote = $quoteFactory->create();

$quoteResource->load($quote, 'test_order_item_with_items', 'reserved_order_id');
if (!$quote->getId()) {
    throw new \RuntimeException('Quote with reserved_order_id test_order_item_with_items not found.');
}

$store = $quote->getStore();
$store->unsetData('current_currency');
$store->setCurrentCurrencyCode('EUR');
$quote->setBaseCurrencyCode('USD');
$quote->setQuoteCurrencyCode('EUR');
$quote->setStoreCurrencyCode('EUR');
$quote->collectTotals();
$quote->save();
