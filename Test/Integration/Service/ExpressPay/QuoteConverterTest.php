<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Service\ExpressPay;

use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Service\ExpressPay\QuoteConverter;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Rate;
use Magento\Quote\Model\Quote\Address\RateResult\Error as AddressRateResultError;
use Magento\Quote\Model\Quote\Item;
use Magento\Tax\Model\Calculation\Rule;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_walk;
use function reset;

class QuoteConverterTest extends TestCase
{
    /**
     * Converts the fixture quote (guest, one simple product, shipping, tax, coupon) and asserts
     * all order_data sections match the quote-derived expectations. Uses key-by-key assertions
     * so the test does not depend on array key order from array_merge_recursive.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     * @magentoDbIsolation enabled
     */
    public function testConvertFullQuoteConvertsNonVirtualQuote(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $cartRepository = $objectManager->get(CartRepositoryInterface::class);
        $searchCriteria = $objectManager->create(SearchCriteriaBuilder::class)
            ->addFilter('reserved_order_id', 'test_order_with_shipping_tax_discount')
            ->create();
        $quotes = $cartRepository->getList($searchCriteria)->getItems();
        self::assertNotEmpty($quotes, 'Fixture quote with reserved_order_id test_order_with_shipping_tax_discount not found');
        /** @var Quote $quote */
        $quote = $cartRepository->get((int) reset($quotes)->getId());

        $productRepository = $objectManager->get(ProductRepositoryInterface::class);
        foreach ($quote->getAllItems() as $item) {
            if (!$item->getProduct()) {
                $item->setProduct($productRepository->getById((int) $item->getProductId()));
            }
        }
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->collectTotals();

        $currencyCode = $quote->getCurrency() !== null
            ? ($quote->getCurrency()->getQuoteCurrencyCode() ?? 'USD')
            : 'USD';
        $grandTotal   = number_format((float) $quote->getGrandTotal(), 2, '.', '');
        $taxTotal     = number_format((float) ($quote->getShippingAddress()->getTaxAmount() ?? 0.0), 2, '.', '');
        $shippingAmt  = number_format((float) $quote->getShippingAddress()->getShippingAmount(), 2, '.', '');

        $gatewayId = 'a31a8fd6-a9e2-4c68-a834-54567bfeb4b7';
        $expected = [
            'gateway_id'  => $gatewayId,
            'order_data' => [
                'locale'                 => 'en-US',
                'customer'               => ['first_name' => 'John', 'last_name' => 'Doe', 'platform_id' => null],
                'shipping_address'       => [
                    'first_name'     => 'John',
                    'last_name'      => 'Doe',
                    'address_line_1' => '123 Test St',
                    'address_line_2' => '',
                    'city'           => 'Los Angeles',
                    'country_code'   => 'US',
                    'postal_code'    => '90001',
                    'state'          => 'California',
                    'phone_number'   => '5555555555',
                ],
                'selected_shipping_option' => [
                    'id'     => 'flatrate_flatrate',
                    'label' => 'Flat Rate - Fixed',
                    'type'   => 'SHIPPING',
                    'amount' => ['currency_code' => $currencyCode, 'value' => $shippingAmt],
                ],
                'shipping_options' => [
                    [
                        'id'     => 'flatrate_flatrate',
                        'label' => 'Flat Rate - Fixed',
                        'type'   => 'SHIPPING',
                        'amount' => ['currency_code' => $currencyCode, 'value' => $shippingAmt],
                    ],
                ],
                'items' => [
                    [
                        'name'                => 'Simple Product',
                        'sku'                 => 'simple',
                        'unit_amount'        => ['currency_code' => $currencyCode, 'value' => '10.00'],
                        'quantity'            => 1,
                        'is_shipping_required' => true,
                    ],
                ],
                'item_total' => ['currency_code' => $currencyCode, 'value' => '10.00'],
                'amount'     => ['currency_code' => $currencyCode, 'value' => $grandTotal],
                'tax_total'  => ['currency_code' => $currencyCode, 'value' => $taxTotal],
                'discount'   => ['currency_code' => $currencyCode, 'value' => '5.00'],
            ],
        ];

        $quoteConverter = $objectManager->create(QuoteConverter::class);
        $result = $quoteConverter->convertFullQuote($quote, $gatewayId);

        self::assertIsArray($result, 'convertFullQuote must return an array');
        self::assertSame($expected['gateway_id'], $result['gateway_id'] ?? null, 'gateway_id');
        self::assertArrayHasKey('order_data', $result, 'order_data');
        $od = $result['order_data'];

        self::assertArrayHasKey('locale', $od);
        self::assertSame($expected['order_data']['locale'], $od['locale'], 'locale');

        self::assertArrayHasKey('customer', $od);
        self::assertEqualsCanonicalizing($expected['order_data']['customer'], $od['customer'], 'customer');

        self::assertArrayHasKey('shipping_address', $od);
        self::assertEqualsCanonicalizing($expected['order_data']['shipping_address'], $od['shipping_address'], 'shipping_address');

        self::assertArrayHasKey('selected_shipping_option', $od);
        self::assertEqualsCanonicalizing($expected['order_data']['selected_shipping_option'], $od['selected_shipping_option'], 'selected_shipping_option');

        self::assertArrayHasKey('shipping_options', $od);
        self::assertIsArray($od['shipping_options']);
        self::assertGreaterThanOrEqual(1, count($od['shipping_options']), 'At least one shipping option');
        $found = false;
        foreach ($od['shipping_options'] as $opt) {
            if (isset($opt['id']) && $opt['id'] === 'flatrate_flatrate') {
                self::assertEqualsCanonicalizing($expected['order_data']['shipping_options'][0], $opt, 'flatrate shipping option');
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Expected flatrate_flatrate in shipping_options');

        self::assertArrayHasKey('items', $od);
        self::assertIsArray($od['items']);
        self::assertCount(1, $od['items']);
        self::assertEqualsCanonicalizing($expected['order_data']['items'][0], $od['items'][0], 'items[0]');

        self::assertArrayHasKey('item_total', $od);
        self::assertEqualsCanonicalizing($expected['order_data']['item_total'], $od['item_total'], 'item_total');

        self::assertArrayHasKey('amount', $od);
        self::assertEqualsCanonicalizing($expected['order_data']['amount'], $od['amount'], 'amount');

        self::assertArrayHasKey('tax_total', $od);
        self::assertEqualsCanonicalizing($expected['order_data']['tax_total'], $od['tax_total'], 'tax_total');

        self::assertArrayHasKey('discount', $od);
        self::assertEqualsCanonicalizing($expected['order_data']['discount'], $od['discount'], 'discount');
    }

    /**
     * @magentoDataFixture Magento/ConfigurableProduct/_files/tax_rule.php
     * @magentoDataFixture Magento/SalesRule/_files/cart_rule_with_coupon_5_off_no_condition.php
     * @magentoDataFixture Magento/Checkout/_files/quote_with_virtual_product_saved.php
     */
    public function testConvertFullQuoteConvertsVirtualQuote(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $searchCriteria = $objectManager->create(SearchCriteriaBuilder::class)
            ->addFilter('reserved_order_id', 'test_order_with_virtual_product_without_address')
            ->create();
        /** @var CartRepositoryInterface $cartRepository */
        $cartRepository = $objectManager->create(CartRepositoryInterface::class);
        $quotes = $cartRepository->getList($searchCriteria)->getItems();
        /** @var Quote $quote */
        $quote = reset($quotes) ?: $objectManager->create(Quote::class);
        /** @var Item[] $quoteItems */
        $quoteItems = $quote->getAllItems();
        /** @var Registry $registry */
        $registry = $objectManager->get(Registry::class);
        /** @var Rule $taxRule */
        $taxRule = $registry->registry('_fixture/Magento_Tax_Model_Calculation_Rule');
        $quoteConverter = $objectManager->create(QuoteConverter::class);

        array_walk(
            $quoteItems,
            static function (Item $item) use ($taxRule): void {
                $item->getProduct()
                    ->setTaxClassId($taxRule->getProductTaxClassIds()[0])
                    ->save();
            }
        );

        $quote->setCouponCode('CART_FIXED_DISCOUNT_5');
        $quote->getBillingAddress()
            ->setFirstname('John')
            ->setLastname('Smith')
            ->setEmail('customer@example.com');

        $cartRepository->save($quote);

        $expectedConvertedQuoteData = [
            'gateway_id' => 'a31a8fd6-a9e2-4c68-a834-54567bfeb4b7',
            'order_data' => [
                'locale' => 'en-US',
                'customer' => [
                    'first_name' => 'John',
                    'last_name' => 'Smith',
                    'email' => 'customer@example.com',
                    'platform_id' => null
                ],
                'items' => [
                    [
                        'name' => 'Virtual Product',
                        'sku' => 'virtual-product',
                        'unit_amount' => [
                            'currency_code' => 'USD',
                            'value' => '10.00'
                        ],
                        'quantity' => 1,
                        'is_shipping_required' => false
                    ]
                ],
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => '5.50'
                ],
                'item_total' => [
                    'currency_code' => 'USD',
                    'value' => '10.00'
                ],
                'tax_total' => [
                    'currency_code' => 'USD',
                    'value' => '0.50'
                ],
                'discount' => [
                    'currency_code' => 'USD',
                    'value' => '5.00'
                ]
            ]
        ];
        $result = $quoteConverter->convertFullQuote(
            $quote,
            'a31a8fd6-a9e2-4c68-a834-54567bfeb4b7'
        );
        self::assertIsArray($result, 'convertFullQuote must return an array');
        $actualConvertedQuoteData = array_filter($result);

        self::assertEquals($expectedConvertedQuoteData, $actualConvertedQuoteData);
    }

    /**
     * Same structure as testConvertFullQuoteConvertsNonVirtualQuote but for a quote in a non-base
     * currency (EUR). Fixture builds from quote_with_shipping_tax_and_discount then sets EUR;
     * we load the quote, derive expected values from it, call convertFullQuote, and assert by key.
     *
     * @magentoConfigFixture current_store currency/options/base USD
     * @magentoConfigFixture current_store currency/options/default USD
     * @magentoConfigFixture current_store currency/options/allow USD,EUR
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_non_base_currency.php
     */
    public function testConvertFullQuoteConvertsNonBaseCurrencyQuote(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $cartRepository = $objectManager->get(CartRepositoryInterface::class);
        $searchCriteria = $objectManager->create(SearchCriteriaBuilder::class)
            ->addFilter('reserved_order_id', 'test_order_1')
            ->create();
        $quotes = $cartRepository->getList($searchCriteria)->getItems();
        self::assertNotEmpty($quotes, 'Fixture quote with reserved_order_id test_order_1 not found');
        /** @var Quote $quote */
        $quote = $cartRepository->get((int) reset($quotes)->getId());

        $productRepository = $objectManager->get(ProductRepositoryInterface::class);
        foreach ($quote->getAllItems() as $item) {
            if (!$item->getProduct()) {
                $item->setProduct($productRepository->getById((int) $item->getProductId()));
            }
        }
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->collectTotals();

        $currencyCode = $quote->getCurrency() !== null
            ? ($quote->getCurrency()->getQuoteCurrencyCode() ?? 'EUR')
            : 'EUR';
        $grandTotal   = number_format((float) $quote->getGrandTotal(), 2, '.', '');
        $taxTotal     = number_format((float) ($quote->getShippingAddress()->getTaxAmount() ?? 0.0), 2, '.', '');
        $shippingAmt  = number_format((float) $quote->getShippingAddress()->getShippingAmount(), 2, '.', '');
        $allItems      = $quote->getAllItems();
        self::assertNotEmpty($allItems, 'Quote must have at least one item');
        $firstItem     = $allItems[0];
        $itemRowTotal  = number_format((float) $firstItem->getRowTotal() / max(1, (float) $firstItem->getQty()), 2, '.', '');
        $itemTotalVal  = number_format((float) $firstItem->getRowTotal(), 2, '.', '');
        $discountValue = number_format(
            (float) ($quote->getSubtotal() - $quote->getSubtotalWithDiscount()),
            2,
            '.',
            ''
        );

        $gatewayId = 'test-gateway-id';
        $expected = [
            'gateway_id'  => $gatewayId,
            'order_data' => [
                'locale'                 => 'en-US',
                'customer'               => ['first_name' => 'John', 'last_name' => 'Doe', 'platform_id' => null],
                'shipping_address'       => [
                    'first_name'     => 'John',
                    'last_name'      => 'Doe',
                    'address_line_1' => '123 Test St',
                    'address_line_2' => '',
                    'city'           => 'Los Angeles',
                    'country_code'   => 'US',
                    'postal_code'    => '90001',
                    'state'          => 'California',
                    'phone_number'   => '5555555555',
                ],
                'selected_shipping_option' => [
                    'id'     => 'flatrate_flatrate',
                    'label' => 'Flat Rate - Fixed',
                    'type'   => 'SHIPPING',
                    'amount' => ['currency_code' => $currencyCode, 'value' => $shippingAmt],
                ],
                'shipping_options' => [
                    [
                        'id'     => 'flatrate_flatrate',
                        'label' => 'Flat Rate - Fixed',
                        'type'   => 'SHIPPING',
                        'amount' => ['currency_code' => $currencyCode, 'value' => $shippingAmt],
                    ],
                ],
                'items' => [
                    [
                        'name'                => 'Simple Product',
                        'sku'                 => 'simple',
                        'unit_amount'        => ['currency_code' => $currencyCode, 'value' => $itemRowTotal],
                        'quantity'            => 1,
                        'is_shipping_required' => true,
                    ],
                ],
                'item_total' => ['currency_code' => $currencyCode, 'value' => $itemTotalVal],
                'amount'     => ['currency_code' => $currencyCode, 'value' => $grandTotal],
                'tax_total'  => ['currency_code' => $currencyCode, 'value' => $taxTotal],
                'discount'   => ['currency_code' => $currencyCode, 'value' => $discountValue],
            ],
        ];

        $quoteConverter = $objectManager->create(QuoteConverter::class);
        $result = $quoteConverter->convertFullQuote($quote, $gatewayId);

        self::assertIsArray($result, 'convertFullQuote must return an array');
        self::assertSame($expected['gateway_id'], $result['gateway_id'] ?? null, 'gateway_id');
        self::assertArrayHasKey('order_data', $result, 'order_data');
        $od = $result['order_data'];

        self::assertArrayHasKey('locale', $od);
        self::assertSame($expected['order_data']['locale'], $od['locale'], 'locale');

        self::assertArrayHasKey('customer', $od);
        self::assertEqualsCanonicalizing($expected['order_data']['customer'], $od['customer'], 'customer');

        self::assertArrayHasKey('shipping_address', $od);
        self::assertEqualsCanonicalizing($expected['order_data']['shipping_address'], $od['shipping_address'], 'shipping_address');

        self::assertArrayHasKey('selected_shipping_option', $od);
        self::assertEqualsCanonicalizing($expected['order_data']['selected_shipping_option'], $od['selected_shipping_option'], 'selected_shipping_option');

        self::assertArrayHasKey('shipping_options', $od);
        self::assertIsArray($od['shipping_options']);
        self::assertGreaterThanOrEqual(1, count($od['shipping_options']), 'At least one shipping option');
        $found = false;
        foreach ($od['shipping_options'] as $opt) {
            if (isset($opt['id']) && $opt['id'] === 'flatrate_flatrate') {
                self::assertEqualsCanonicalizing($expected['order_data']['shipping_options'][0], $opt, 'flatrate shipping option');
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Expected flatrate_flatrate in shipping_options');

        self::assertArrayHasKey('items', $od);
        self::assertIsArray($od['items']);
        self::assertCount(1, $od['items']);
        self::assertEqualsCanonicalizing($expected['order_data']['items'][0], $od['items'][0], 'items[0]');

        self::assertArrayHasKey('item_total', $od);
        self::assertEqualsCanonicalizing($expected['order_data']['item_total'], $od['item_total'], 'item_total');

        self::assertArrayHasKey('amount', $od);
        self::assertEqualsCanonicalizing($expected['order_data']['amount'], $od['amount'], 'amount');

        self::assertArrayHasKey('tax_total', $od);
        self::assertEqualsCanonicalizing($expected['order_data']['tax_total'], $od['tax_total'], 'tax_total');

        self::assertArrayHasKey('discount', $od);
        self::assertEqualsCanonicalizing($expected['order_data']['discount'], $od['discount'], 'discount');

        self::assertSame('EUR', $currencyCode, 'Quote should be in EUR (non-base currency)');
    }

    public function testDoesNotConvertShippingInformationIfAddressIsNotSet(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteConverter $quoteConverter */
        $quoteConverter = $objectManager->create(QuoteConverter::class);

        self::assertEmpty($quoteConverter->convertShippingInformation($quote));
    }

    public function testDoesNotConvertShippingInformationIfAddressRateHasError(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteConverter $quoteConverter */
        $quoteConverter = $objectManager->create(QuoteConverter::class);
        /** @var Rate $rate */
        $rate = $objectManager->create(Rate::class);
        /** @var AddressRateResultError $erroneousShippingRateResult */
        $erroneousShippingRateResult = $objectManager->create(AddressRateResultError::class);
        $shippingAddress = $quote->getShippingAddress();

        $shippingAddress->setId(42);
        $shippingAddress->addShippingRate($rate);

        $rate->importShippingRate($erroneousShippingRateResult);

        self::assertEmpty($quoteConverter->convertShippingInformation($quote)['order_data']);
    }

    public function testDoesNotConvertCustomerIfBillingAddressIsNotSet(): void
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteConverter $quoteConverter */
        $quoteConverter = $objectManager->create(QuoteConverter::class);

        self::assertEmpty($quoteConverter->convertCustomer($quote));
    }

    /**
     * When billing firstname/lastname are null AND isUseShippingNameAsFallback is false,
     * convertCustomer() must fall back to the hard-coded sentinel values ('noname' / 'nolastname').
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_named_shipping_unnamed_billing.php
     * @magentoDbIsolation enabled
     */
    public function testConvertCustomerUsesDefaultSentinelWhenBillingNameNullAndFallbackDisabled(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $searchCriteria = $objectManager->create(SearchCriteriaBuilder::class)
            ->addFilter('reserved_order_id', 'test_order_1')
            ->create();
        $quotes = $objectManager->create(CartRepositoryInterface::class)
            ->getList($searchCriteria)
            ->getItems();
        /** @var Quote $quote */
        $quote = reset($quotes) ?: $objectManager->create(Quote::class);

        $billingAddress = $quote->getBillingAddress();
        $billingAddress->setId($billingAddress->getId() ?? 1);
        $billingAddress->setFirstname(null)->setLastname(null);
        $quote->getShippingAddress()->setFirstname('Jane')->setLastname('Doe');

        /** @var Config|\PHPUnit\Framework\MockObject\MockObject $config */
        $config = $this->createMock(Config::class);
        $config->method('isUseShippingNameAsFallback')->willReturn(false);

        /** @var QuoteConverter $quoteConverter */
        $quoteConverter = $objectManager->create(QuoteConverter::class, ['config' => $config]);

        $result = $quoteConverter->convertCustomer($quote);
        self::assertArrayHasKey('order_data', $result, 'convertCustomer must return order_data when billing address is set');
        self::assertSame('noname', $result['order_data']['customer']['first_name']);
        self::assertSame('nolastname', $result['order_data']['customer']['last_name']);
    }

    /**
     * When billing firstname/lastname are null AND isUseShippingNameAsFallback is true,
     * convertCustomer() must use the shipping address name.
     *
     * Uses the fixture purpose-built for CHK-9535: billing has no personal name while
     * shipping retains the full name.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_named_shipping_unnamed_billing.php
     * @magentoDbIsolation enabled
     */
    public function testConvertCustomerUsesShippingNameWhenBillingNameNullAndFallbackEnabled(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $searchCriteria = $objectManager->create(SearchCriteriaBuilder::class)
            ->addFilter('reserved_order_id', 'test_order_1')
            ->create();
        $quotes = $objectManager->create(CartRepositoryInterface::class)
            ->getList($searchCriteria)
            ->getItems();
        /** @var Quote $quote */
        $quote = reset($quotes) ?: $objectManager->create(Quote::class);

        // The fixture already has billing firstname/lastname = ''; set to null to exercise
        // the null-coalescing path in convertCustomer() ('' is treated as falsy in the ternary).
        $quote->getBillingAddress()->setFirstname(null)->setLastname(null);
        // Shipping name from the fixture is "John Smith" — use a distinct name here to
        // clearly differentiate the source of the value in the assertion.
        $quote->getShippingAddress()->setFirstname('Jane')->setLastname('Doe');

        /** @var Config|\PHPUnit\Framework\MockObject\MockObject $config */
        $config = $this->createMock(Config::class);
        $config->method('isUseShippingNameAsFallback')->willReturn(true);

        /** @var QuoteConverter $quoteConverter */
        $quoteConverter = $objectManager->create(QuoteConverter::class, ['config' => $config]);

        $result = $quoteConverter->convertCustomer($quote);

        self::assertSame('Jane', $result['order_data']['customer']['first_name']);
        self::assertSame('Doe', $result['order_data']['customer']['last_name']);
    }
}
