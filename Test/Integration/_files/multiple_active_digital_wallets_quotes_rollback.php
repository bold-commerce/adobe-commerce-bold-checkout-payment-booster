<?php

declare(strict_types=1);

use Magento\Quote\Model\ResourceModel\Quote\Collection as QuoteCollection;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Catalog/_files/products_rollback.php');

$objectManager = Bootstrap::getObjectManager();
/** @var QuoteCollection $quoteCollection */
$quoteCollection = $objectManager->create(QuoteCollection::class);

$quoteCollection->addFieldToFilter('is_digital_wallets', '1');
$quoteCollection->load();
$quoteCollection->walk('delete');
