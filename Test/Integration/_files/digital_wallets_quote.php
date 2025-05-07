<?php

declare(strict_types=1);

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Checkout/_files/quote_with_address.php');

$objectManager = Bootstrap::getObjectManager();
/** @var QuoteResourceModel $quoteResourceModel */
$quoteResourceModel = $objectManager->create(QuoteResourceModel::class);
/** @var Quote $quote */
$quote = $objectManager->create(Quote::class);

$quoteResourceModel->load($quote, 'test_order_1', 'reserved_order_id');

$quote->setReservedOrderId('digital_wallets_order_1');
$quote->setData('is_digital_wallets', true);
$quote->save();
