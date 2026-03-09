<?php

declare(strict_types=1);

use Magento\Quote\Model\Quote;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$objectManager = Bootstrap::getObjectManager();
/** @var Quote $quote */
$quote = $objectManager->create(Quote::class);
$quote->load('test_order_1', 'reserved_order_id');
if ($quote->getId()) {
    $quote->delete();
}

Resolver::getInstance()->requireDataFixture('Magento/Customer/_files/customer_rollback.php');
Resolver::getInstance()->requireDataFixture('Magento/Customer/_files/customer_address_rollback.php');
