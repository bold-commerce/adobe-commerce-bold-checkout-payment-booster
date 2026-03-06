<?php

declare(strict_types=1);

use Magento\Framework\Registry;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/** @var Order $order */
$order = $objectManager->create(Order::class);
$order->loadByIncrementId('100000001');

if ($order->getId()) {
    /** @var OrderRepositoryInterface $orderRepository */
    $orderRepository = $objectManager->create(OrderRepositoryInterface::class);
    $orderRepository->delete($order);
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
