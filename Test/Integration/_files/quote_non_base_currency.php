<?php
/**
 * Applies coupon CART_FIXED_DISCOUNT_5 and switches the quote currency to EUR.
 *
 * Declare Magento/ConfigurableProduct/_files/tax_rule.php,
 * Magento/SalesRule/_files/cart_rule_with_coupon_5_off_no_condition.php, and
 * Magento/Checkout/_files/quote_with_address.php as @magentoDataFixture prerequisites.
 * Configure currency allow-list via @magentoConfigFixture before this fixture.
 */

declare(strict_types=1);

use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);
/** @var QuoteResource $quoteResource */
$quoteResource = $objectManager->get(QuoteResource::class);

$quote = $quoteFactory->create();
$quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

$store = $quote->getStore();
$store->unsetData('current_currency');
$store->setCurrentCurrencyCode('EUR');

$quote->setBaseCurrencyCode('USD');
$quote->setQuoteCurrencyCode('EUR');
// Set the USD→EUR rate used to convert base amounts to quote currency amounts.
// Expected test values (1.06, 3.53, 7.07, 14.14, 18.74) are all base × 0.707.
$quote->setBaseToQuoteRate(0.707);
$quote->setStoreToBaseRate(1.0);
$quote->setCouponCode('CART_FIXED_DISCOUNT_5');
$quote->save();
