<?php

declare(strict_types=1);

use Magento\Framework\App\ProductMetadata;
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

$objectManager = Bootstrap::getObjectManager();
/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);
/** @var QuoteResource $quoteResource */
$quoteResource = $objectManager->get(QuoteResource::class);
$quote = $quoteFactory->create();

/** @var  $productRepository */
$productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);


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

$product = $quote->getItems()[0]->getProduct();

$productMetadata = $objectManager->create(ProductMetadata::class);
$magentoVersion =  $this->_productMetadata->getVersion();
echo $magentoVersion;
if ($magentoVersion == "2.4.3-p3" ) {
    $product->setExtensionAttributes(
        $objectManager->create(\Magento\Catalog\Api\Data\ProductExtension::class)
            ->setStockItem(
                $objectManager->create(\Magento\CatalogInventory\Api\Data\StockItemInterface::class)
                    ->setIsInStock(true)
                    ->setQty(100)
            )
    );
    $productRepository->save($product);
}
