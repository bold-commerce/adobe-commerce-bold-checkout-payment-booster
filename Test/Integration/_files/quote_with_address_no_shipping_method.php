<?php

declare(strict_types=1);

/**
 * Fixture: a quote that has a complete shipping address (city + country populated so
 * QuoteConverter::convertShippingInformation() can find shipping rates) but has NO
 * shipping method selected on it.
 *
 * This is used to test that:
 *   - selected_shipping_option is NOT included in the wallet_pay payload
 *   - the order `amount` does NOT include a shipping component
 *
 * Base quote: Magento/Checkout/_files/quote_with_address_saved.php
 *   reserved_order_id = 'test_order_1'
 */

use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Checkout/_files/quote_with_address_saved.php');

$objectManager = Bootstrap::getObjectManager();
/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);
/** @var QuoteResource $quoteResource */
$quoteResource = $objectManager->get(QuoteResource::class);

$quote = $quoteFactory->create();
$quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

// Explicitly guarantee no shipping method is set: some base fixtures may pre-select one.
$shippingAddress = $quote->getShippingAddress();
$shippingAddress->setShippingMethod(null)
    ->setShippingDescription(null)
    ->setShippingAmount(0)
    ->setBaseShippingAmount(0)
    ->save();

$quote->collectTotals()->save();
