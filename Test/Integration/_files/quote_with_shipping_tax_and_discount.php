<?php

declare(strict_types=1);

use Magento\Framework\Registry;
use Magento\Quote\Model\Quote\Address\Rate;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Tax\Model\Calculation\Rule;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/ConfigurableProduct/_files/tax_rule.php');
Resolver::getInstance()->requireDataFixture('Magento/SalesRule/_files/cart_rule_with_coupon_5_off_no_condition.php');
Resolver::getInstance()->requireDataFixture('Magento/Checkout/_files/quote_with_address_saved.php');

$resource = Bootstrap::getObjectManager()->get(\Magento\Framework\App\ResourceConnection::class);
$connection = $resource->getConnection();

$connection->query("
    INSERT IGNORE INTO inventory_source_item (source_code, sku, quantity, status)
    SELECT 'default', sku, qty, stock_status
    FROM cataloginventory_stock_status AS lg
    JOIN catalog_product_entity AS prd ON lg.product_id = prd.entity_id
");

$connection->query("
    INSERT IGNORE INTO inventory_source_stock_link (stock_id, source_code, priority)
    VALUES (1, 'default', 1)
");


$objectManager = Bootstrap::getObjectManager();
/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);
/** @var QuoteResource $quoteResource */
$quoteResource = $objectManager->get(QuoteResource::class);
$quote = $quoteFactory->create();

$quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

$shippingAddress = $quote->getShippingAddress();

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
/** @var Rule $taxRule */
$taxRule = $registry->registry('_fixture/Magento_Tax_Model_Calculation_Rule');
/** @var Item[] $quoteItems */
$quoteItems = $quote->getAllItems();

array_walk(
    $quoteItems,
    static function (Item $item) use ($taxRule): void {
        $item->getProduct()
            ->setTaxClassId($taxRule->getProductTaxClassIds()[0])
            ->save();
    }
);

$quote->setCouponCode('CART_FIXED_DISCOUNT_5');
$quote->save();
