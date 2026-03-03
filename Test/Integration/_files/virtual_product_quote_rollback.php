<?php

declare(strict_types=1);

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Catalog/_files/product_virtual_rollback.php');

$objectManager = Bootstrap::getObjectManager();
/** @var SearchCriteriaBuilder $searchCriteriaBuilder */
$searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
$searchCriteria = $searchCriteriaBuilder
    ->addFilter('reserved_order_id', 'virtual_test_order_1')
    ->create();
/** @var CartRepositoryInterface $cartRepository */
$cartRepository = $objectManager->create(CartRepositoryInterface::class);

$quotes = $cartRepository->getList($searchCriteria)->getItems();

/** @var Quote $quote */
foreach ($quotes as $quote) {
    $cartRepository->delete($quote);
}
