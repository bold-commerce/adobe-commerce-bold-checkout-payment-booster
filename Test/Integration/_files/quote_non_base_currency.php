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
$quoteResource->load($quote, 'test_order_1', 'reserved_order_id');
$store = $quote->getStore();
$store->unsetData('current_currency');
$store->setCurrentCurrencyCode('CNY');
$quote->setBaseCurrencyCode('USD');
$quote->setQuoteCurrencyCode('CNY');
//$quote->setHaseForcedCurrency(true);
$quote->save();
