<?php

declare(strict_types=1);

use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Applies coupon CART_FIXED_DISCOUNT_5 to the quote created by
 * Magento/Checkout/_files/quote_with_address.php (reserved_order_id = 'test_order_1').
 *
 * Declare Magento/SalesRule/_files/cart_rule_with_coupon_5_off_no_condition.php
 * and Magento/Checkout/_files/quote_with_address.php as @magentoDataFixture prerequisites.
 */
$objectManager = Bootstrap::getObjectManager();

/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);
/** @var QuoteResource $quoteResource */
$quoteResource = $objectManager->get(QuoteResource::class);

$quote = $quoteFactory->create();
$quoteResource->load($quote, 'test_order_1', 'reserved_order_id');
$quote->setCouponCode('CART_FIXED_DISCOUNT_5');
$quoteResource->save($quote);
