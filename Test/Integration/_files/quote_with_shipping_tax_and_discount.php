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

// Tax rule and sales rule are applied by the test's @magentoDataFixture; re-applying them causes "Code already exists".
Resolver::getInstance()->requireDataFixture('Magento/Checkout/_files/quote_with_address_saved.php');

$objectManager = Bootstrap::getObjectManager();
/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);
/** @var QuoteResource $quoteResource */
$quoteResource = $objectManager->get(QuoteResource::class);
$quote = $quoteFactory->create();

$quoteResource->load($quote, 'test_order_1', 'reserved_order_id');
$quote->setReservedOrderId('test_order_with_shipping_tax_discount');
$quoteResource->save($quote);

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
$quote->save();
