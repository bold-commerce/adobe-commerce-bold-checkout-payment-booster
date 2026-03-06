<?php

declare(strict_types=1);

use Magento\Catalog\Api\ProductRepositoryInterface; 
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Model\ResourceModel\Quote\Item as QuoteItemResource;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Tax\Model\Calculation\Rule;
use Magento\TestFramework\Helper\Bootstrap;

// Sub-fixtures (tax_rule.php, cart_rule, product_simple) are declared as @magentoDataFixture
// on the test method. Do NOT use requireDataFixture() here.

$objectManager = Bootstrap::getObjectManager();

/** @var \Magento\Catalog\Api\ProductCustomOptionRepositoryInterface $optionRepository */
$optionRepository = $objectManager->get(\Magento\Catalog\Api\ProductCustomOptionRepositoryInterface::class);

/** @var StoreManagerInterface $storeManager */
$storeManager = $objectManager->get(StoreManagerInterface::class);
$storeId = (int)$storeManager->getStore()->getId();

/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);

/** @var Rule $taxRule */
$taxRule = $objectManager->create(Rule::class)->load('Test Rule', 'code');
if (!$taxRule->getId()) {
    throw new \RuntimeException(
        'Tax rule "Test Rule" was not found. Ensure Magento/ConfigurableProduct/_files/tax_rule.php '
        . 'is declared as a @magentoDataFixture before this fixture.'
    );
}

try {
    $product = $productRepository->get('simple', false, $storeId, true);
} catch (NoSuchEntityException $e) {
    throw new \RuntimeException('Required fixture product with SKU "simple" was not found.', 0, $e);
}

$product->setTaxClassId((int)$taxRule->getProductTaxClassIds()[0]);
$productRepository->save($product);

// 1) Create quote + mask via guest API (same as create_empty_cart.php).
/** @var GuestCartManagementInterface $guestCartManagement */
$guestCartManagement = $objectManager->get(GuestCartManagementInterface::class);
$maskedId = $guestCartManagement->createEmptyCart();

// 2) Load the same quote instance created by the guest API and keep working on it.
/** @var \Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface $maskedToQuoteId */
$maskedToQuoteId = $objectManager->get(\Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface::class);
$quoteId = (int)$maskedToQuoteId->execute($maskedId);
/** @var CartRepositoryInterface $cartRepository */
$cartRepository = $objectManager->get(CartRepositoryInterface::class);
$quote = $cartRepository->getActive($quoteId);
if (!$quote->getId()) {
    throw new \RuntimeException('Active quote for the generated masked cart was not found.');
}

$quote->setReservedOrderId('test_order_1');
$quote->setStoreId($storeId);
$quote->setIsActive(true);
$quote->setCheckoutMethod(CartManagementInterface::METHOD_GUEST);
$quote->setCustomerIsGuest(true);
$quote->setCustomerEmail('john.doe@example.com');

$addressData = [
    'firstname'  => 'John',
    'lastname'   => 'Doe',
    'street'     => '123 Test St',
    'city'       => 'Los Angeles',
    'region'     => 'California',
    'region_id'  => 12,
    'postcode'   => '90001',
    'country_id' => 'US',
    'telephone'  => '5555555555',
];
$quote->getShippingAddress()->addData($addressData);
$quote->getShippingAddress()->setCollectShippingRates(true);
$quote->getShippingAddress()->setShippingMethod('flatrate_flatrate');
$quote->getShippingAddress()->setShippingDescription('Flat Rate - Fixed');
$quote->getShippingAddress()->setBaseShippingAmount(5.00);
$quote->getShippingAddress()->setShippingAmount(5.00);
$quote->getBillingAddress()->addData($addressData);
$quote->setInventoryProcessed(false);
$quote->getShippingAddress()->setCollectShippingRates(true);
$quote->getShippingAddress()->collectShippingRates();

// Build buy request with required custom options (product_simple.php has required text, date_time, drop_down, radio).
$requestData = ['qty' => 1, 'options' => []];
foreach ($optionRepository->getList($product->getSku()) as $option) {
    if (!$option->getIsRequire()) {
        continue;
    }
    $optionId = $option->getOptionId();
    switch ($option->getType()) {
        case 'field':
            $requestData['options'][$optionId] = 'test';
            break;
        case 'date_time':
            $requestData['options'][$optionId] = [
                'year' => (int)date('Y'),
                'month' => (int)date('n'),
                'day' => (int)date('j'),
                'hour' => (int)date('G'),
                'minute' => (int)date('i'),
            ];
            break;
        case 'drop_down':
        case 'radio':
            $values = $option->getValues();
            if ($values !== null && $values !== []) {
                $firstValue = reset($values);
                if ($firstValue !== false) {
                    $requestData['options'][$optionId] = $firstValue->getOptionTypeId();
                }
            }
            break;
        default:
            $requestData['options'][$optionId] = '1';
    }
}
$request = new \Magento\Framework\DataObject($requestData);
$result = $quote->addProduct($product, $request);
if (is_string($result)) {
    throw new \RuntimeException('Failed adding product to quote: ' . $result);
}
// Persist items explicitly so they are in quote_item before cartRepository->save() (CI-safe).
/** @var QuoteItemResource $quoteItemResource */
$quoteItemResource = $objectManager->get(QuoteItemResource::class);
foreach ($quote->getAllItems() as $item) {
    if ($item->getQuoteId() === null) {
        $item->setQuoteId((int)$quote->getId());
    }
    $quoteItemResource->save($item);
}

$quote->setItemsCount((int)count($quote->getAllVisibleItems()));
$quote->setItemsQty((float)array_sum(array_map(static fn($item) => (float)$item->getQty(), $quote->getAllVisibleItems())));
$quote->setCouponCode('CART_FIXED_DISCOUNT_5');
$quote->collectTotals();
$cartRepository->save($quote);

$quote = $cartRepository->get($quoteId);
if (!(int)$quote->getItemsCount() || !count($quote->getAllVisibleItems())) {
    throw new \RuntimeException('Fixture quote was saved without visible items.');
}
