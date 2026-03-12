<?php

/**
 * Fixture: a non-virtual guest quote with shipping, tax, and a coupon discount.
 *
 * This fixture intentionally avoids the Magento/Checkout fixture chain
 * (quote_with_address_saved → quote_with_address → Magento/Catalog/_files/products.php)
 * because products.php creates 'custom-design-simple-product' with a hardcoded entity_id=2.
 * When stale catalog_product_link rows referencing '24-UG01' exist in the test DB, the
 * product SaveHandler tries to delete those links via the ProductLink\Repository, which
 * internally calls getProductLinkId() — and if the IDs don't match (e.g. after an earlier
 * test run left inconsistent data), it throws NoSuchEntityException and the fixture fails.
 *
 * Instead we create only what our tests actually need:
 *   - A 'simple' product created via the ProductRepository API (no hardcoded entity_id,
 *     idempotent: loads an existing product by SKU before creating a new one).
 *   - A customer + address loaded from the standard Magento customer fixtures.
 *   - A guest quote with the address fields the assertions expect.
 *
 * Tax rule and sales rule are applied by the test's @magentoDataFixture; re-applying
 * them here would cause "Code already exists".
 */

declare(strict_types=1);

use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Checkout\Model\Type\Onepage;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Registry;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Rate;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Tax\Model\Calculation\Rule;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Customer/_files/customer.php');
Resolver::getInstance()->requireDataFixture('Magento/Customer/_files/customer_address.php');

$objectManager = Bootstrap::getObjectManager();

// ---------------------------------------------------------------------------
// 0. Clean up any pre-existing quote with reserved_order_id='test_order_1' so
//    the fixture is idempotent across multiple test-suite runs.
// ---------------------------------------------------------------------------
/** @var \Magento\Quote\Model\ResourceModel\Quote\Collection $quoteCollection */
$quoteCollection = $objectManager->create(\Magento\Quote\Model\ResourceModel\Quote\Collection::class);
$quoteCollection->addFieldToFilter('reserved_order_id', 'test_order_1');
foreach ($quoteCollection as $existingQuote) {
    $existingQuote->delete();
}

// ---------------------------------------------------------------------------
// 1. Create (or load) the 'simple' product via the repository API.
//    Using the repository avoids hardcoded entity_id values and the stale
//    product-link issue that arises when products.php is used directly.
// ---------------------------------------------------------------------------
/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);
/** @var ProductInterfaceFactory $productFactory */
$productFactory = $objectManager->get(ProductInterfaceFactory::class);

try {
    $product = $productRepository->get('simple', false, null, true);
} catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
    $product = $productFactory->create();
}

$product->setTypeId('simple')
    ->setAttributeSetId(4)
    ->setWebsiteIds([1])
    ->setName('Simple Product')
    ->setSku('simple')
    ->setPrice(10)
    ->setMetaTitle('meta title')
    ->setMetaKeyword('meta keyword')
    ->setMetaDescription('meta description')
    ->setVisibility(Visibility::VISIBILITY_BOTH)
    ->setStatus(Status::STATUS_ENABLED)
    ->setStockData(['use_config_manage_stock' => 1, 'qty' => 22, 'is_in_stock' => 1])
    // Clear any required custom options (e.g. from product_simple.php) so that
    // Quote::addProduct() can add this item without requiring option values.
    ->setOptions([])
    ->setCanSaveCustomOptions(true)
    ->setHasOptions(false);

$product = $productRepository->save($product);

// ---------------------------------------------------------------------------
// 2. Build the quote from the customer fixture data.
// ---------------------------------------------------------------------------
/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
/** @var AddressRepositoryInterface $addressRepository */
$addressRepository = $objectManager->get(AddressRepositoryInterface::class);
/** @var AccountManagementInterface $accountManagement */
$accountManagement = $objectManager->get(AccountManagementInterface::class);

$customer = $customerRepository->getById(1);
$quoteShippingAddress = $objectManager->create(\Magento\Quote\Model\Quote\Address::class);
$quoteShippingAddress->importCustomerAddressData($addressRepository->getById(1));

/** @var Quote $quote */
$quote = $objectManager->create(Quote::class);
$quote->setStoreId(1)
    ->setIsActive(true)
    ->setIsMultiShipping(false)
    ->assignCustomerWithAddressChange($customer)
    ->setShippingAddress($quoteShippingAddress)
    ->setBillingAddress($quoteShippingAddress)
    ->setCheckoutMethod('customer')
    ->setPasswordHash($accountManagement->getPasswordHash('password'))
    ->setReservedOrderId('test_order_1')
    ->setCustomerEmail('aaa@aaa.com')
    ->addProduct($product, 2);
$quote->save();

// ---------------------------------------------------------------------------
// 3. Reload and convert to a guest quote with the address the tests expect.
// ---------------------------------------------------------------------------
/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);
/** @var QuoteResource $quoteResource */
$quoteResource = $objectManager->get(QuoteResource::class);
$quote = $quoteFactory->create();
$quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

// Tests expect a guest quote (John Doe, 123 Test St) rather than the customer quote.
$quote->setCustomerId(null)
    ->setCustomerEmail(null)
    ->setCheckoutMethod(Onepage::METHOD_GUEST)
    ->setCustomerIsGuest(true);

// Use region_id 12 (California) so getRegion() returns "California".
$guestAddressData = [
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

$shippingAddress = $quote->getShippingAddress();
$shippingAddress->setFirstname($guestAddressData['firstname'])
    ->setLastname($guestAddressData['lastname'])
    ->setStreet($guestAddressData['street'])
    ->setCity($guestAddressData['city'])
    ->setRegion($guestAddressData['region'])
    ->setRegionId($guestAddressData['region_id'])
    ->setPostcode($guestAddressData['postcode'])
    ->setCountryId($guestAddressData['country_id'])
    ->setTelephone($guestAddressData['telephone'])
    ->setCustomerAddressId(null);

$billingAddress = $quote->getBillingAddress();
$billingAddress->setFirstname($guestAddressData['firstname'])
    ->setLastname($guestAddressData['lastname'])
    ->setStreet($guestAddressData['street'])
    ->setCity($guestAddressData['city'])
    ->setRegion($guestAddressData['region'])
    ->setRegionId($guestAddressData['region_id'])
    ->setPostcode($guestAddressData['postcode'])
    ->setCountryId($guestAddressData['country_id'])
    ->setTelephone($guestAddressData['telephone'])
    ->setCustomerAddressId(null);

// Tests expect one item with qty 1; the quote was created with qty 2.
foreach ($quote->getAllItems() as $item) {
    $item->setQty(1);
}

$shippingAddress->setShippingMethod('flatrate_flatrate')
    ->setShippingDescription('Flat Rate - Fixed')
    ->save();

$rate = $objectManager->get(Rate::class);
$rate->setPrice(5.00)
    ->setAddressId($shippingAddress->getId())
    ->save();

$shippingAddress->setBaseShippingAmount($rate->getPrice());
$shippingAddress->setShippingAmount($rate->getPrice());

$rate->delete();

$registry = $objectManager->get(Registry::class);
/** @var Rule|null $taxRule */
$taxRule = $registry->registry('_fixture/Magento_Tax_Model_Calculation_Rule');
if ($taxRule === null) {
    $taxRuleCollection = $objectManager->get(\Magento\Tax\Model\ResourceModel\Calculation\Rule\Collection::class);
    $taxRule = $taxRuleCollection->addFieldToFilter('code', 'Test Rule')->getFirstItem();
}
/** @var Item[] $quoteItems */
$quoteItems = $quote->getAllItems();
if ($taxRule && $taxRule->getId()) {
    $productTaxClassIds = $taxRule->getProductTaxClassIds();
    if (!empty($productTaxClassIds)) {
        array_walk(
            $quoteItems,
            static function (Item $item) use ($productTaxClassIds): void {
                $item->getProduct()
                    ->setTaxClassId($productTaxClassIds[0])
                    ->save();
            }
        );
    }
}

$quote->setCouponCode('CART_FIXED_DISCOUNT_5');
$quote->collectTotals();
$quote->save();

// Create a QuoteIdMask so callers can resolve the quote by its masked ID.
// The original quote_with_address_saved.php fixture did this; without it,
// Create::execute() cannot resolve the quote from the mask and falls back to
// the checkout session (which has no active quote in integration tests).
/** @var QuoteIdMaskFactory $quoteIdMaskFactory */
$quoteIdMaskFactory = $objectManager->get(QuoteIdMaskFactory::class);
$quoteIdMask = $quoteIdMaskFactory->create();
$quoteIdMask->setQuoteId($quote->getId());
$quoteIdMask->setDataChanges(true);
$quoteIdMask->save();
