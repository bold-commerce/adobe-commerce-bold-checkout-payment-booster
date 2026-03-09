<?php

/**
 * Fixture: a quote whose only item is a virtual product.
 *
 * A virtual quote causes Quote::getIsVirtual() to return true, which makes
 * QuoteConverter::convertShippingInformation() return [] immediately (no shipping data
 * of any kind is added to the wallet_pay payload).
 *
 * This is used to test that:
 *   - shipping_address is absent from the wallet_pay payload
 *   - shipping_options is absent from the wallet_pay payload
 *   - selected_shipping_option is absent from the wallet_pay payload
 *
 * Uses a Magento core virtual product (SKU 'virtual-product') and a customer.
 *   reserved_order_id = 'virtual_test_order_1'
 */

declare(strict_types=1);

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Model\Quote\Address as QuoteAddress;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Catalog/_files/product_virtual.php');

$objectManager = Bootstrap::getObjectManager();
/** @var StoreManagerInterface $storeManager */
$storeManager = $objectManager->get(StoreManagerInterface::class);
/** @var CartInterfaceFactory $cartFactory */
$cartFactory = $objectManager->get(CartInterfaceFactory::class);
/** @var CartRepositoryInterface $cartRepository */
$cartRepository = $objectManager->get(CartRepositoryInterface::class);
/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);

$store = $storeManager->getStore();

/** @var \Magento\Quote\Model\Quote $quote */
$quote = $cartFactory->create();
$quote->setReservedOrderId('virtual_test_order_1');
$quote->setStoreId($store->getId());
$quote->setIsActive(true);
$quote->setIsMultiShipping(false);
$quote->setCustomerIsGuest(true);
$quote->setCustomerEmail('virtual-customer@example.com');

$virtualProduct = $productRepository->get('virtual-product');
$quote->addProduct($virtualProduct, 1);

// Billing address only — virtual orders have no shipping address.
/** @var QuoteAddress $billingAddress */
$billingAddress = $objectManager->create(QuoteAddress::class);
$billingAddress->setFirstname('Jane')
    ->setLastname('Doe')
    ->setEmail('virtual-customer@example.com')
    ->setStreet(['456 Test Ave'])
    ->setCity('Los Angeles')
    ->setRegionCode('CA')
    ->setRegionId(12)
    ->setPostcode('90001')
    ->setCountryId('US')
    ->setTelephone('555-9876')
    ->setAddressType('billing');

$quote->setBillingAddress($billingAddress);

// collectTotals() will set is_virtual = 1 automatically because all items are virtual.
$quote->collectTotals();
$cartRepository->save($quote);
