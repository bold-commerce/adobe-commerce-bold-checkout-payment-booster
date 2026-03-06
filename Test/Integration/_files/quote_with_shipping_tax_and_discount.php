<?php

declare(strict_types=1);

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Calculation\Rule;
use Magento\TestFramework\Helper\Bootstrap;

// Sub-fixtures (tax_rule.php, cart_rule, product_simple) are declared as @magentoDataFixture
// on the test method. Do NOT use requireDataFixture() here.

$objectManager = Bootstrap::getObjectManager();

/** @var StoreManagerInterface $storeManager */
$storeManager = $objectManager->get(StoreManagerInterface::class);
$storeId = (int)$storeManager->getStore()->getId();

/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);

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

$product->setTaxClassId((int)$taxRule->getProductTaxClassIds()[0]);
$productRepository->save($product);

// 1) Create quote + mask via guest API (same as create_empty_cart.php).
/** @var GuestCartManagementInterface $guestCartManagement */
$guestCartManagement = $objectManager->get(GuestCartManagementInterface::class);
$maskedId = $guestCartManagement->createEmptyCart();

// 2) Persist reserved_order_id so CreateTest::getQuote() can find this quote and resolve its mask.
/** @var \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedToQuoteId */
$maskedToQuoteId = $objectManager->get(\Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface::class);
$quoteId = (int)$maskedToQuoteId->execute($maskedId);
/** @var CartRepositoryInterface $cartRepository */
$cartRepository = $objectManager->get(CartRepositoryInterface::class);
$quote = $cartRepository->get($quoteId);
$quote->setReservedOrderId('test_order_1');
$cartRepository->save($quote);

// 3) Load by reserved_order_id with QuoteResource (exact add_simple_product pattern) then add item.
/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);
/** @var QuoteResource $quoteResource */
$quoteResource = $objectManager->get(QuoteResource::class);
$quote = $quoteFactory->create();
$quoteResource->load($quote, 'test_order_1', 'reserved_order_id');
if (!$quote->getId()) {
    throw new \RuntimeException('Quote with reserved_order_id test_order_1 was not found.');
}

$addressData = [
    'firstname'  => 'John',
    'lastname'   => 'Doe',
    'street'     => '123 Test St',
    'city'       => 'Los Angeles',
    'region'     => 'California',
    'region_id'  => 12,
    'postcode'   => '90001',
    'country_id' => 'US',
    'telephone'  => '5555555555',
];
$quote->getShippingAddress()->addData($addressData);
$quote->getShippingAddress()->setCollectShippingRates(true);
$quote->getShippingAddress()->setShippingMethod('flatrate_flatrate');
$quote->getShippingAddress()->setShippingDescription('Flat Rate - Fixed');
$quote->getShippingAddress()->setBaseShippingAmount(5.00);
$quote->getShippingAddress()->setShippingAmount(5.00);
$quote->getBillingAddress()->addData($addressData);

$quote->addProduct($product, 1);
$quote->setCouponCode('CART_FIXED_DISCOUNT_5');
$quote->collectTotals();
$cartRepository->save($quote);
