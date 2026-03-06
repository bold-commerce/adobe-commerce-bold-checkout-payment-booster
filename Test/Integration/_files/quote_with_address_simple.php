<?php

declare(strict_types=1);

use Magento\Catalog\Model\Product;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Customer/_files/customer.php');
Resolver::getInstance()->requireDataFixture('Magento/Customer/_files/customer_address.php');

$objectManager = Bootstrap::getObjectManager();

// Create a single simple product (same as first product in products.php, no second product = no link error).
/** @var Product $product */
$product = $objectManager->create(Product::class);
$product->setTypeId('simple')
    ->setAttributeSetId(4)
    ->setWebsiteIds([1])
    ->setName('Simple Product')
    ->setSku('simple')
    ->setPrice(10)
    ->setMetaTitle('meta title')
    ->setMetaKeyword('meta keyword')
    ->setMetaDescription('meta description')
    ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
    ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
    ->setStockData(['use_config_manage_stock' => 1, 'qty' => 22, 'is_in_stock' => 1])
    ->save();

/** @var QuoteAddress $quoteShippingAddress */
$quoteShippingAddress = $objectManager->create(QuoteAddress::class);
/** @var AccountManagementInterface $accountManagement */
$accountManagement = $objectManager->get(AccountManagementInterface::class);
/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
$customer = $customerRepository->getById(1);
/** @var AddressRepositoryInterface $addressRepository */
$addressRepository = $objectManager->get(AddressRepositoryInterface::class);
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

// Collect shipping rates so the address has rates (converter needs getShippingRatesCollection() to be non-empty).
$quote->getShippingAddress()->setCollectShippingRates(true);
$quote->collectTotals();
$quote->getShippingAddress()
    ->setShippingMethod('flatrate_flatrate')
    ->setShippingDescription('Flat Rate - Fixed')
    ->setShippingAmount(10)
    ->setBaseShippingAmount(10);
$quote->collectTotals();
// Override shipping amount to match test expectation (10.00 USD); flatrate default may be different.
$quote->getShippingAddress()->setShippingAmount(10)->setBaseShippingAmount(10);
$quote->save();
