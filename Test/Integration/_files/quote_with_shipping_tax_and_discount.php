<?php

declare(strict_types=1);

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Calculation\Rule;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

// Tax rule + sales rule fixtures
Resolver::getInstance()->requireDataFixture('Magento/ConfigurableProduct/_files/tax_rule.php');
Resolver::getInstance()->requireDataFixture('Magento/SalesRule/_files/cart_rule_with_coupon_5_off_no_condition.php');

// Base simple product fixture (SKU: simple)
Resolver::getInstance()->requireDataFixture('Magento/Catalog/_files/product_simple.php');

$objectManager = Bootstrap::getObjectManager();

/** @var StoreManagerInterface $storeManager */
$storeManager = $objectManager->get(StoreManagerInterface::class);
$storeId = (int)$storeManager->getStore()->getId();

/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);

/** @var Rule|null $taxRule */
$taxRule = $objectManager->get(\Magento\Framework\Registry::class)
    ->registry('_fixture/Magento_Tax_Model_Calculation_Rule');

// The registry key varies across Magento versions; fall back to the first rule in the DB.
if (!$taxRule || !$taxRule->getId()) {
    /** @var \Magento\Tax\Model\ResourceModel\Calculation\Rule\Collection $ruleCollection */
    $ruleCollection = $objectManager->create(\Magento\Tax\Model\ResourceModel\Calculation\Rule\Collection::class);
    $ruleCollection->setPageSize(1)->load();
    $taxRule = $ruleCollection->getFirstItem() ?: null;
}

$productTaxClassId = ($taxRule && $taxRule->getId())
    ? (int) ($taxRule->getProductTaxClassIds()[0] ?? 2)
    : 2; // 2 = "Taxable Goods" — the default Magento product tax class

try {
    $product = $productRepository->get('simple', false, $storeId, true);
} catch (NoSuchEntityException $e) {
    throw new \RuntimeException('Required fixture product with SKU "simple" was not found.', 0, $e);
}

// Apply tax class to product BEFORE adding it to the quote (avoid saving products from quote items)
$product->setTaxClassId($productTaxClassId);
$productRepository->save($product);

/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);
/** @var QuoteResource $quoteResource */
$quoteResource = $objectManager->get(QuoteResource::class);

$quote = $quoteFactory->create();
$quote->setStoreId($storeId);
$quote->setIsActive(true);
$quote->setReservedOrderId('test_order_1');

// Add product
$quote->addProduct($product, 1);

// Addresses (minimal required fields for totals + shipping)
$addressData = [
    'firstname' => 'John',
    'lastname' => 'Doe',
    'street' => '123 Test St',
    'city' => 'Los Angeles',
    'region' => 'California',
    'region_id' => 12, // CA
    'postcode' => '90001',
    'country_id' => 'US',
    'telephone' => '5555555555',
];

$shippingAddress = $quote->getShippingAddress();
$shippingAddress->addData($addressData);
$shippingAddress->setCollectShippingRates(true);
$shippingAddress->setShippingMethod('flatrate_flatrate');
$shippingAddress->setShippingDescription('Flat Rate - Fixed');

$billingAddress = $quote->getBillingAddress();
$billingAddress->addData($addressData);

// Set a deterministic shipping amount for the test
$shippingAddress->setBaseShippingAmount(5.00);
$shippingAddress->setShippingAmount(5.00);

// Apply coupon
$quote->setCouponCode('CART_FIXED_DISCOUNT_5');

// Collect totals and persist
$quote->collectTotals();
$quoteResource->save($quote);
