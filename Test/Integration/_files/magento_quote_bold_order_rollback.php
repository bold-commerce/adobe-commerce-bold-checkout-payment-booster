<?php

declare(strict_types=1);

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\MagentoQuoteBoldOrder;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\MagentoQuoteBoldOrder as MagentoQuoteBoldOrderResourceModel;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Checkout/_files/quote_with_items_saved_rollback.php');

$objectManager = Bootstrap::getObjectManager();
/** @var MagentoQuoteBoldOrder $magentoQuoteBoldOrder */
$magentoQuoteBoldOrder = $objectManager->create(MagentoQuoteBoldOrder::class);
/** @var MagentoQuoteBoldOrderResourceModel $magentoQuoteBoldOrderResourceModel */
$magentoQuoteBoldOrderResourceModel = $objectManager->create(MagentoQuoteBoldOrderResourceModel::class);
/** @var MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository */
$magentoQuoteBoldOrderRepository = $objectManager->create(MagentoQuoteBoldOrderRepositoryInterface::class);

$magentoQuoteBoldOrderResourceModel->load(
    $magentoQuoteBoldOrder,
    'e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0',
    'bold_order_id'
);

$magentoQuoteBoldOrderRepository->delete($magentoQuoteBoldOrder);
