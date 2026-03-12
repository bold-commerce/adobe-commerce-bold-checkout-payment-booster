<?php

declare(strict_types=1);

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Registry;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$objectManager = Bootstrap::getObjectManager();

// Delete the quote (and its mask) created by this fixture.
/** @var Quote $quote */
$quote = $objectManager->create(Quote::class);
$quote->load('test_order_1', 'reserved_order_id');
if ($quote->getId()) {
    /** @var QuoteIdMaskResource $quoteIdMaskResource */
    $quoteIdMaskResource = $objectManager->get(QuoteIdMaskResource::class);
    $quoteIdMaskResource->getConnection()->delete(
        $quoteIdMaskResource->getMainTable(),
        ['quote_id = ?' => $quote->getId()]
    );
    $quote->delete();
}

// Delete the 'simple' product created by this fixture.
/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);
try {
    $product = $productRepository->get('simple', false, null, true);
    $productRepository->delete($product);
} catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
    // Product already removed.
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);

Resolver::getInstance()->requireDataFixture('Magento/Customer/_files/customer_address_rollback.php');
Resolver::getInstance()->requireDataFixture('Magento/Customer/_files/customer_rollback.php');
