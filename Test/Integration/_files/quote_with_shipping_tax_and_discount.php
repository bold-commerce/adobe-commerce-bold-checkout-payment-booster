<?php

declare(strict_types=1);

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Quote\Model\ResourceModel\Quote\QuoteIdMask as QuoteIdMaskResource;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Calculation\Rule;
use Magento\TestFramework\Helper\Bootstrap;

// Sub-fixtures (tax_rule.php, cart_rule, product_simple) are declared as @magentoDataFixture
// annotations on the test method. Do NOT use requireDataFixture() here — the Resolver's
// "already loaded" cache can become out of sync with the Registry after @magentoAppIsolation
// resets the app, causing the registry entry to be null.

$objectManager = Bootstrap::getObjectManager();

/** @var StoreManagerInterface $storeManager */
$storeManager = $objectManager->get(StoreManagerInterface::class);
$storeId = (int)$storeManager->getStore()->getId();

/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);

// Load the tax rule directly from the DB instead of relying on the Registry,
// which may be empty if app isolation cleared it between test classes.
/** @var Rule $taxRule */
$taxRule = $objectManager->create(Rule::class)->load('Test Rule', 'code');
if (!$taxRule->getId()) {
    throw new \RuntimeException(
        'Tax rule "Test Rule" was not found. Ensure Magento/ConfigurableProduct/_files/tax_rule.php '
        . 'is declared as a @magentoDataFixture before this fixture.'
    );
}

try {
    $product = $productRepository->get('simple', false, $storeId, true);
} catch (NoSuchEntityException $e) {
    throw new \RuntimeException('Required fixture product with SKU "simple" was not found.', 0, $e);
}

// Apply tax class to product BEFORE adding it to the quote.
$product->setTaxClassId((int)$taxRule->getProductTaxClassIds()[0]);
$productRepository->save($product);

/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);
/** @var QuoteResource $quoteResource */
$quoteResource = $objectManager->get(QuoteResource::class);

// --- Step 1: Persist the bare quote first so it receives a real entity_id. ---
// Item::setQuote() stamps each item with $quote->getId() at the moment addProduct()
// is called. If the quote has no ID yet (entity_id = null) the item is written to
// quote_item with quote_id = null and never appears in subsequent loads.
// Saving the header row first guarantees every item created below gets the real ID.
$quote = $quoteFactory->create();
$quote->setStoreId($storeId);
$quote->setIsActive(true);
$quote->setReservedOrderId('test_order_1');

// Addresses (minimal required fields for totals + shipping rates)
$addressData = [
    'firstname'  => 'John',
    'lastname'   => 'Doe',
    'street'     => '123 Test St',
    'city'       => 'Los Angeles',
    'region'     => 'California',
    'region_id'  => 12, // CA
    'postcode'   => '90001',
    'country_id' => 'US',
    'telephone'  => '5555555555',
];

$shippingAddress = $quote->getShippingAddress();
$shippingAddress->addData($addressData);
$shippingAddress->setCollectShippingRates(true);
$shippingAddress->setShippingMethod('flatrate_flatrate');
$shippingAddress->setShippingDescription('Flat Rate - Fixed');
$shippingAddress->setBaseShippingAmount(5.00);
$shippingAddress->setShippingAmount(5.00);

$billingAddress = $quote->getBillingAddress();
$billingAddress->addData($addressData);

// Save the quote header now — $quote->getId() is set to the real entity_id after this.
$quoteResource->save($quote);

// --- Step 2: Add item and coupon now that the quote has a real entity_id. ---
// Item::setQuote($quote) → setQuoteId($quote->getId()) now stamps the correct ID.
$quote->addProduct($product, 1);
$quote->setCouponCode('CART_FIXED_DISCOUNT_5');

// --- Step 3: Collect totals and persist (saves quote + items via Relation composite). ---
$quote->collectTotals();
$quoteResource->save($quote);

// --- Step 4: Create a quote_id_mask record so tests can resolve the mask. ---
// Without this, QuoteIdToMaskedQuoteId::execute() throws NoSuchEntityException,
// causing test helpers that return '' which makes Create/Update use the checkout
// session (an empty, inactive quote) instead of this fixture quote.
/** @var QuoteIdMaskFactory $quoteIdMaskFactory */
$quoteIdMaskFactory = $objectManager->get(QuoteIdMaskFactory::class);
$quoteIdMask = $quoteIdMaskFactory->create();
$quoteIdMask->setQuoteId($quote->getId());
/** @var QuoteIdMaskResource $quoteIdMaskResource */
$quoteIdMaskResource = $objectManager->get(QuoteIdMaskResource::class);
$quoteIdMaskResource->save($quoteIdMask);
