<?php

declare(strict_types=1);

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\MagentoQuoteBoldOrder;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/Checkout/_files/quote_with_items_saved.php');

$objectManager = Bootstrap::getObjectManager();
/** @var QuoteResourceModel $quoteResourceModel */
$quoteResourceModel = $objectManager->create(QuoteResourceModel::class);
/** @var Quote $quote */
$quote = $objectManager->create(Quote::class);
/** @var MagentoQuoteBoldOrder $magentoQuoteBoldOrder */
$magentoQuoteBoldOrder = $objectManager->create(MagentoQuoteBoldOrder::class);
/** @var MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository */
$magentoQuoteBoldOrderRepository = $objectManager->create(MagentoQuoteBoldOrderRepositoryInterface::class);

$quoteResourceModel->load($quote, 'test_order_item_with_items', 'reserved_order_id');

$magentoQuoteBoldOrder
    ->setBoldOrderId('e5537d5a79264a53995b9ccf6b86225b46925006f6e24a59a8892fbb524b1aa0')
    ->setQuoteId($quote->getId());

$magentoQuoteBoldOrderRepository->save($magentoQuoteBoldOrder);
