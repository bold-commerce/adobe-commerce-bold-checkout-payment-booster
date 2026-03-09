<?php

/**
 * Fixture: a quote that reproduces Johnny's billing-address scenario.
 *
 * The billing address has NO personal name (simulating a business/corporate billing
 * account, e.g. "BJK SERVICE CENTRE" saved without a first/last name), while the
 * shipping address retains the full personal name ("John Smith").
 *
 * This fixture is used to guard against two related bugs:
 *
 * 1. Create.php — shipping method cleared incorrectly:
 *    OLD: $hasBillingData checked billing firstname only. With billing first_name = "",
 *         $hasBillingData was false, so the shipping method was wiped before
 *         convertFullQuote ran. The wallet_pay amount excluded shipping ($25.92 instead
 *         of $34.82), and PayPal authorized the wrong amount → 422 on payments/auth/full.
 *    FIXED: shipping method is only cleared when BOTH billing AND shipping lack data.
 *
 * 2. QuoteConverter.php — empty customer name sent to PayPal:
 *    OLD: 'first_name' => $billingAddress->getFirstname() ?? ''
 *         ?? only guards null; Magento returns '' for unset fields, so the wallet_pay
 *         payload had customer.first_name = "".
 *    FIXED: billing ?: shipping ?: '' handles both null and empty string.
 *
 * Uses only customer + address fixtures (no catalog products) so that quote save
 * never touches product links and avoids errors like 24-UG01 / custom-design-simple-product.
 */

declare(strict_types=1);

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
/** @var AddressRepositoryInterface $addressRepository */
$addressRepository = $objectManager->get(AddressRepositoryInterface::class);
/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
/** @var AccountManagementInterface $accountManagement */
$accountManagement = $objectManager->get(AccountManagementInterface::class);

$customer = $customerRepository->getById(1);
$customerAddress = $addressRepository->getById(1);

$shippingAddress = $objectManager->create(QuoteAddress::class);
$shippingAddress->importCustomerAddressData($customerAddress);
$shippingAddress->setFirstname('John')->setLastname('Smith');

$billingAddress = $objectManager->create(QuoteAddress::class);
$billingAddress->importCustomerAddressData($customerAddress);
$billingAddress->setFirstname('')->setLastname('');

/** @var Quote $quote */
$quote = $objectManager->create(Quote::class);
$quote->setStoreId(1)
    ->setIsActive(true)
    ->setIsMultiShipping(false)
    ->assignCustomerWithAddressChange($customer)
    ->setShippingAddress($shippingAddress)
    ->setBillingAddress($billingAddress)
    ->setCheckoutMethod('customer')
    ->setPasswordHash($accountManagement->getPasswordHash('password'))
    ->setReservedOrderId('test_order_1')
    ->setCustomerEmail('aaa@aaa.com');
// Intentionally no addProduct() — avoids any product/link logic on save.

$quote->save();
