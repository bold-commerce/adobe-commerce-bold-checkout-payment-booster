<?php
/**
 * Fixture: a quote that reproduces Johnny's billing-address scenario.
 *
 * The billing address has NO personal name (simulating a business/corporate billing
 * account, e.g. "BJK SERVICE CENTRE" saved without a first/last name), while the
 * shipping address retains the full personal name ("John Smith").
 *
 * A shipping method is already selected on the quote.
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
 * Base quote: Magento/Checkout/_files/quote_with_address_saved.php
 *   reserved_order_id = 'test_order_1'
 */

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

$objectManager = Bootstrap::getObjectManager();
/** @var QuoteFactory $quoteFactory */
$quoteFactory = $objectManager->get(QuoteFactory::class);
/** @var QuoteResource $quoteResource */
$quoteResource = $objectManager->get(QuoteResource::class);
$quote = $quoteFactory->create();

$quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

// ── Set up shipping method (mirrors quote_with_shipping_tax_and_discount.php) ─
$shippingAddress = $quote->getShippingAddress();

$shippingAddress->setShippingMethod('flatrate_flatrate')
    ->setShippingDescription('Flat Rate - Fixed')
    ->save();

/** @var Rate $rate */
$rate = $objectManager->get(Rate::class);

$rate->setPrice(5.00)
    ->setAddressId($shippingAddress->getId())
    ->save();

$shippingAddress->setBaseShippingAmount($rate->getPrice());
$shippingAddress->setShippingAmount($rate->getPrice());

$rate->delete();

/** @var Registry $registry */
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

// ── Johnny's specific condition: billing address has no personal name ──────────
// The base fixture sets both billing and shipping to "John Smith".
// We clear the billing name to simulate a business billing account where the
// company (not a person) is the billed entity. The shipping address name is kept
// so $hasShippingData = true in the fixed Create.php guard.
$billingAddress = $quote->getBillingAddress();
$billingAddress->setFirstname('')->setLastname('');

$quote->save();
