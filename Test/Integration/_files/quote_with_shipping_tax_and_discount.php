<?php

declare(strict_types=1);

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
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

// Load the tax rule directly from the DB instead of relying on the Registry.
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

// Create quote and mask via guest cart API so quote has a real ID and mask from the start.
/** @var GuestCartManagementInterface $guestCartManagement */
$guestCartManagement = $objectManager->get(GuestCartManagementInterface::class);
$maskedId = $guestCartManagement->createEmptyCart();

/** @var MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId */
$maskedQuoteIdToQuoteId = $objectManager->get(MaskedQuoteIdToQuoteIdInterface::class);
$quoteId = (int)$maskedQuoteIdToQuoteId->execute($maskedId);

/** @var CartRepositoryInterface $cartRepository */
$cartRepository = $objectManager->get(CartRepositoryInterface::class);
$quote = $cartRepository->get($quoteId);

// Required so CreateTest::getQuote() can find this quote by reserved_order_id.
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

$quote->addProduct($product, 1);
$quote->setCouponCode('CART_FIXED_DISCOUNT_5');

$quote->collectTotals();
$cartRepository->save($quote);
