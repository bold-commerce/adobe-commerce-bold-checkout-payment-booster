<?php

/**
 * Minimal self-contained order fixture.
 *
 * Creates an order with increment_id 100000001, a saved payment, and billing/shipping
 * addresses. No dependency on Magento core test fixtures, so the test suite is insulated
 * from cross-version changes in Magento's own fixture files (e.g. order.php, which
 * internally requires order_with_invoice.php in some Magento versions).
 */

declare(strict_types=1);

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

$addressData = [
    'region'     => 'California',
    'region_id'  => 12,
    'postcode'   => '11111',
    'lastname'   => 'lastname',
    'firstname'  => 'firstname',
    'street'     => 'street',
    'city'       => 'Los Angeles',
    'email'      => 'admin@example.com',
    'telephone'  => '11111111',
    'country_id' => 'US',
];

/** @var OrderAddress $billingAddress */
$billingAddress = $objectManager->create(OrderAddress::class, ['data' => $addressData]);
$billingAddress->setAddressType('billing');

$shippingAddress = clone $billingAddress;
$shippingAddress->setId(null)->setAddressType('shipping');

/** @var Payment $payment */
$payment = $objectManager->create(Payment::class);
$payment->setMethod('checkmo');

/** @var Order $order */
$order = $objectManager->create(Order::class);
$order->setIncrementId('100000001')
    ->setState(Order::STATE_PROCESSING)
    ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING))
    ->setSubtotal(100)
    ->setGrandTotal(100)
    ->setBaseSubtotal(100)
    ->setBaseGrandTotal(100)
    ->setOrderCurrencyCode('USD')
    ->setBaseCurrencyCode('USD')
    ->setCustomerIsGuest(true)
    ->setCustomerEmail('customer@example.com')
    ->setBillingAddress($billingAddress)
    ->setShippingAddress($shippingAddress)
    ->setStoreId($objectManager->get(StoreManagerInterface::class)->getStore()->getId())
    ->setPayment($payment);

/** @var OrderRepositoryInterface $orderRepository */
$orderRepository = $objectManager->create(OrderRepositoryInterface::class);
$orderRepository->save($order);
