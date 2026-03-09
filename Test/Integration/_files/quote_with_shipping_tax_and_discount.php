<?php

declare(strict_types=1);

use Magento\Catalog\Api\ProductCustomOptionRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Calculation\Rule;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$objectManager = Bootstrap::getObjectManager();
/** @var ProductRepositoryInterface $productRepositoryCheck */
$productRepositoryCheck = $objectManager->get(ProductRepositoryInterface::class);
try {
    $productRepositoryCheck->get('simple', false, null, true);
} catch (NoSuchEntityException $e) {
    // Test did not declare dependencies; load them (e.g. QuoteConverterTest with only this fixture).
    Resolver::getInstance()->requireDataFixture('Magento/ConfigurableProduct/_files/tax_rule.php');
    Resolver::getInstance()->requireDataFixture('Magento/SalesRule/_files/cart_rule_with_coupon_5_off_no_condition.php');
    Resolver::getInstance()->requireDataFixture('Magento/Catalog/_files/product_simple.php');
}

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

// Remove required custom options (product_simple fixture adds them) so we can add product without option values
/** @var ProductCustomOptionRepositoryInterface $optionRepository */
$optionRepository = $objectManager->get(ProductCustomOptionRepositoryInterface::class);
$optionIds = array_map(
    static fn ($option) => $option->getOptionId(),
    $optionRepository->getList('simple')
);
foreach ($optionIds as $optionId) {
    $optionRepository->deleteByIdentifier('simple', $optionId);
}

// Apply tax class and save
$product = $productRepository->get('simple', false, $storeId, true);
$product->setTaxClassId($productTaxClassId);
$productRepository->save($product);

// Create quote via guest cart API so it is registered as active and has a mask
/** @var GuestCartManagementInterface $guestCartManagement */
$guestCartManagement = $objectManager->get(GuestCartManagementInterface::class);
$maskedId = $guestCartManagement->createEmptyCart();
/** @var MaskedQuoteIdToQuoteIdInterface $maskedToQuoteId */
$maskedToQuoteId = $objectManager->get(MaskedQuoteIdToQuoteIdInterface::class);
$quoteId = (int)$maskedToQuoteId->execute($maskedId);
/** @var CartRepositoryInterface $cartRepository */
$cartRepository = $objectManager->get(CartRepositoryInterface::class);
$quote = $cartRepository->get($quoteId);

$quote->setStoreId($storeId);
$quote->setReservedOrderId('test_order_with_shipping_tax_discount');
$quote->setCustomerIsGuest(true);
$quote->setCheckoutMethod('guest');

// Add product (use request to avoid option validation issues)
$request = new \Magento\Framework\DataObject(['qty' => 1]);
$result = $quote->addProduct($product, $request);
if (is_string($result)) {
    throw new \RuntimeException('Failed to add product to quote: ' . $result);
}

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

// Persist via CartRepository so quote items are saved (QuoteResource alone does not persist items)
$quote->collectTotals();
$cartRepository->save($quote);
