<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Service\ExpressPay;

use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\Config\Source\GatewayPriceFormat;
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
     * The fixture (quote_with_shipping_tax_and_discount.php) was rebuilt from scratch in
     * CHK-9535 to be compatible with Magento 2.4.6-p10.
     *
     * New fixture characteristics:
     *  - One simple product (SKU: simple, price $10.00, qty 1)
     *  - Billing + shipping: John Doe, 123 Test St, Los Angeles CA 90001 US, tel 5555555555
     *  - Shipping method: flatrate_flatrate ("Flat Rate - Fixed"), amount $5.00
     *  - Coupon: CART_FIXED_DISCOUNT_5 → $5.00 discount
     *  - Guest quote (no customer) → no email, platform_id = null
     *
     * Financial totals (grand total, tax) are derived from the live quote after
     * collectTotals() so the test remains correct across different Magento tax configs.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     * @magentoDbIsolation enabled
     */
    public function testConvertFullQuoteConvertsNonVirtualQuote(): void
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

        // CartRepositoryInterface::getList() does not hydrate the product model on quote items.
        // The tax calculator calls $item->getProduct()->getProductTaxClassIds() during
        // collectTotals(), which fatal-errors when getProduct() returns null.
        // Force-load the product for every item before collecting totals.
        $productRepository = $objectManager->get(ProductRepositoryInterface::class);
        foreach ($quote->getAllItems() as $item) {
            if (!$item->getProduct()) {
                $item->setProduct($productRepository->getById((int) $item->getProductId()));
            }
        }

        // Pre-collect totals so we can derive the expected financial values from the quote.
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->collectTotals();

        $currencyCode  = $quote->getCurrency() !== null
            ? ($quote->getCurrency()->getQuoteCurrencyCode() ?? 'USD')
            : 'USD';
        $grandTotal    = number_format((float) $quote->getGrandTotal(), 2, '.', '');
        $taxTotal      = number_format(
            (float) ($quote->getShippingAddress()->getTaxAmount() ?? 0.0),
            2,
            '.',
            ''
        );
        $shippingAmt   = number_format(
            (float) $quote->getShippingAddress()->getShippingAmount(),
            2,
            '.',
            ''
        );

        $quoteConverter = $objectManager->create(QuoteConverter::class);

        $expectedConvertedQuoteData = [
            'gateway_id' => 'a31a8fd6-a9e2-4c68-a834-54567bfeb4b7',
            'order_data' => [
                'locale' => 'en-US',
                'customer' => [
                    'first_name'  => 'John',
                    'last_name'   => 'Doe',
                    'platform_id' => null,
                ],
                'shipping_address' => [
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
                    'id'    => 'flatrate_flatrate',
                    'label' => 'Flat Rate - Fixed',
                    'type'  => 'SHIPPING',
                    'amount' => [
                        'currency_code' => $currencyCode,
                        'value'         => $shippingAmt,
                    ],
                ],
                'shipping_options' => [
                    [
                        'id'    => 'flatrate_flatrate',
                        'label' => 'Flat Rate - Fixed',
                        'type'  => 'SHIPPING',
                        'amount' => [
                            'currency_code' => $currencyCode,
                            'value'         => $shippingAmt,
                        ],
                    ],
                ],
                'items' => [
                    [
                        'name'        => 'Simple Product',
                        'sku'         => 'simple',
                        'unit_amount' => [
                            'currency_code' => $currencyCode,
                            'value'         => '10.00',
                        ],
                        'quantity'             => 1,
                        'is_shipping_required' => true,
                    ],
                ],
                'item_total' => [
                    'currency_code' => $currencyCode,
                    'value'         => '10.00',
                ],
                'amount' => [
                    'currency_code' => $currencyCode,
                    'value'         => $grandTotal,
                ],
                'tax_total' => [
                    'currency_code' => $currencyCode,
                    'value'         => $taxTotal,
                ],
                'discount' => [
                    'currency_code' => $currencyCode,
                    'value'         => '5.00',
                ],
            ],
        ];

        $actualConvertedQuoteData = $quoteConverter->convertFullQuote($quote, 'a31a8fd6-a9e2-4c68-a834-54567bfeb4b7');

        self::assertEqualsCanonicalizing($expectedConvertedQuoteData, $actualConvertedQuoteData);
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
        $actualConvertedQuoteData = array_filter(
            $quoteConverter->convertFullQuote(
                $quote,
                'a31a8fd6-a9e2-4c68-a834-54567bfeb4b7'
            )
        );

        self::assertEquals($expectedConvertedQuoteData, $actualConvertedQuoteData);
    }

    /**
     * @magentoConfigFixture current_store currency/options/base USD
     * @magentoConfigFixture current_store currency/options/default USD
     * @magentoConfigFixture current_store currency/options/allow USD,EUR
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_non_base_currency.php
     */
    public function testConvertFullQuoteConvertsNonBaseCurrencyQuote(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var SearchCriteriaInterface $searchCriteria */
        $searchCriteria = $objectManager->create(SearchCriteriaBuilder::class)
            ->addFilter('reserved_order_id', 'test_order_1')
            ->create();
        /** @var CartInterface[] $searchResult */
        $searchResult = $objectManager->create(CartRepositoryInterface::class)
            ->getList($searchCriteria)
            ->getItems();
        /** @var Quote $quote */
        $quote = reset($searchResult);
        /** @var QuoteConverter $quoteConverter */
        $quoteConverter = $objectManager->create(QuoteConverter::class);

        $expectedConvertedQuoteData = [
            'gateway_id' => 'test-gateway-id',
            'order_data' => [
                'locale' => 'en-US',
                'customer' => [
                    'first_name' => 'John',
                    'last_name' => 'Smith',
                    'email' => 'customer@example.com',
                    'platform_id' => '1'
                ],
                'shipping_address' => [
                    'first_name' => 'John',
                    'last_name' => 'Smith',
                    'address_line_1' => 'Green str, 67',
                    'address_line_2' => '',
                    'city' => 'CityM',
                    'country_code' => 'US',
                    'postal_code' => '75477',
                    'state' => 'Alabama',
                    'phone_number' => '3468676',
                ],
                'selected_shipping_option' => [
                    'id' => 'flatrate_flatrate',
                    'label' => 'Flat Rate - Fixed',
                    'type' => 'SHIPPING',
                    'amount' => [
                        'currency_code' => 'EUR',
                        'value' => '7.07',
                    ],
                ],
                'shipping_options' => [
                    [
                        'id' => 'flatrate_flatrate',
                        'label' => 'Flat Rate - Fixed',
                        'type' => 'SHIPPING',
                        'amount' => [
                            'currency_code' => 'EUR',
                            'value' => '7.07',
                        ],
                    ],
                ],
                'items' => [
                    [
                        'name' => 'Simple Product',
                        'sku' => 'simple',
                        'unit_amount' => [
                            'currency_code' => 'EUR',
                            'value' => '7.07',
                        ],
                        'quantity' => 2,
                        'is_shipping_required' => true,
                    ],
                ],
                'amount' => [
                    'currency_code' => 'EUR',
                    'value' => '18.74',
                ],
                'item_total' => [
                    'currency_code' => 'EUR',
                    'value' => '14.14',
                ],
                'tax_total' => [
                    'currency_code' => 'EUR',
                    'value' => '1.06',
                ],
                'discount' => [
                    'currency_code' => 'EUR',
                    'value' => '3.53',
                ],
            ],
        ];
        $actualConvertedQuoteData = $quoteConverter->convertFullQuote($quote, 'test-gateway-id');

        self::assertEqualsCanonicalizing($expectedConvertedQuoteData, $actualConvertedQuoteData);
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

    // ─── CHK-9534: gateway price formatting modes ─────────────────────────────

    /**
     * In INCLUDE_TAX mode the unit_amount for each item must be rowTotalInclTax / qty
     * (greater than the base price) so that the item total sent to Bold already contains tax.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     * @magentoDbIsolation enabled
     */
    public function testConvertQuoteItemsUsesRowTotalInclTaxAsUnitPriceInIncludeTaxMode(): void
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

        /** @var Config|\PHPUnit\Framework\MockObject\MockObject $config */
        $config = $this->createMock(Config::class);
        $config->method('isGatewayPriceFormattingEnabled')->willReturn(true);
        $config->method('isTaxIncludedInPrices')->willReturn(true);
        $config->method('getGatewayPriceFormat')->willReturn(GatewayPriceFormat::INCLUDE_TAX);
        $config->method('isTaxIncludedInShipping')->willReturn(false);
        $config->method('isUseShippingNameAsFallback')->willReturn(false);
        $config->method('getPriceFormatLineItemTitle')->willReturn('Rounding');

        /** @var QuoteConverter $quoteConverter */
        $quoteConverter = $objectManager->create(QuoteConverter::class, ['config' => $config]);

        $result = $quoteConverter->convertQuoteItems($quote);

        $items = $result['order_data']['items'] ?? [];
        self::assertNotEmpty($items, 'Items array must not be empty.');

        foreach ($items as $item) {
            $unitValue = (float) $item['unit_amount']['value'];
            // In INCLUDE_TAX mode the unit price must exceed the base tax-exclusive price ($10.00)
            self::assertGreaterThan(
                10.00,
                $unitValue,
                sprintf(
                    'INCLUDE_TAX unit price (%s) must be greater than the base price (10.00). '
                    . 'Verify rowTotalInclTax / qty is used.',
                    $unitValue
                )
            );
        }
    }

    /**
     * In INCLUDE_TAX mode, convertTaxes() must return only the non-item portion of the address
     * tax (e.g. shipping tax), NOT the full address tax amount — deducting item taxes prevents
     * double-counting because item unit_amounts already include their tax.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     * @magentoDbIsolation enabled
     */
    public function testConvertTaxesDeductsItemTaxesInIncludeTaxMode(): void
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

        /** @var Config|\PHPUnit\Framework\MockObject\MockObject $config */
        $config = $this->createMock(Config::class);
        $config->method('isGatewayPriceFormattingEnabled')->willReturn(true);
        $config->method('isTaxIncludedInPrices')->willReturn(true);
        $config->method('getGatewayPriceFormat')->willReturn(GatewayPriceFormat::INCLUDE_TAX);
        $config->method('isTaxIncludedInShipping')->willReturn(false);
        $config->method('isUseShippingNameAsFallback')->willReturn(false);
        $config->method('getPriceFormatLineItemTitle')->willReturn('Rounding');

        // Force-load the product model for each quote item so the tax calculator
        // can call getProductTaxClassIds() without hitting a null reference.
        $productRepository = $objectManager->get(ProductRepositoryInterface::class);
        foreach ($quote->getAllItems() as $item) {
            if (!$item->getProduct()) {
                $item->setProduct($productRepository->getById((int) $item->getProductId()));
            }
        }

        // Collect the full quote first so tax amounts are populated
        $quote->collectTotals();

        $itemsTaxAmount = 0.0;
        foreach ($quote->getAllItems() as $item) {
            $itemsTaxAmount += (float) ($item->getTaxAmount() ?? 0.0);
        }
        $addressTaxAmount = (float) ($quote->getShippingAddress()->getTaxAmount() ?? 0.0);

        // Skip if there is no address tax to assert against
        if ($addressTaxAmount <= 0.0) {
            self::markTestSkipped('Fixture has no address tax — cannot verify INCLUDE_TAX deduction.');
        }

        /** @var QuoteConverter $quoteConverter */
        $quoteConverter = $objectManager->create(QuoteConverter::class, ['config' => $config]);

        $result = $quoteConverter->convertTaxes($quote);
        $taxTotalValue = (float) ($result['order_data']['tax_total']['value'] ?? 0.0);

        $expectedNonItemTax = max(0.0, round($addressTaxAmount - $itemsTaxAmount, 2));
        self::assertEqualsWithDelta(
            $expectedNonItemTax,
            $taxTotalValue,
            0.01,
            'In INCLUDE_TAX mode tax_total must equal address tax minus item taxes (non-item tax only).'
        );
        // Confirm the returned tax is strictly less than the full address tax
        self::assertLessThan(
            $addressTaxAmount,
            $taxTotalValue + 0.005,
            'INCLUDE_TAX tax_total must be less than the full address tax to avoid double-counting.'
        );
    }

    /**
     * In EXCLUDE_TAX mode a rounding_adjustment line item must be appended when
     * sum(unit_price * qty) differs from Magento's subtotal by >= $0.01.
     *
     * The fixture product price is $10.00 and uses integer quantities, so no natural
     * rounding delta exists. We inject an item with a non-round price to force the delta.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     * @magentoDbIsolation enabled
     */
    public function testConvertQuoteItemsAddsRoundingAdjustmentItemInExcludeTaxMode(): void
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

        // Force a rounding delta: set price to $9.99 so 2×$9.99 = $19.98,
        // then override the quote's subtotal to $20.00 — the $0.02 gap triggers the adjustment.
        foreach ($quote->getAllItems() as $item) {
            $item->setPrice(9.99);
            $item->setRowTotal(19.98);
            break; // Affect only the first item; one is enough
        }
        $quote->getShippingAddress()->setSubtotal(20.00);

        /** @var Config|\PHPUnit\Framework\MockObject\MockObject $config */
        $config = $this->createMock(Config::class);
        $config->method('isGatewayPriceFormattingEnabled')->willReturn(true);
        $config->method('isTaxIncludedInPrices')->willReturn(true); // required to activate a non-LEGACY mode
        $config->method('getGatewayPriceFormat')->willReturn(GatewayPriceFormat::EXCLUDE_TAX);
        $config->method('isTaxIncludedInShipping')->willReturn(false);
        $config->method('isUseShippingNameAsFallback')->willReturn(false);
        $config->method('getPriceFormatLineItemTitle')->willReturn('Rounding');

        /** @var QuoteConverter $quoteConverter */
        $quoteConverter = $objectManager->create(QuoteConverter::class, ['config' => $config]);

        $result = $quoteConverter->convertQuoteItems($quote);
        $itemSkus = array_column($result['order_data']['items'] ?? [], 'sku');

        self::assertContains(
            'rounding_adjustment',
            $itemSkus,
            'A rounding_adjustment item must be present when sum(unit*qty) diverges from Magento subtotal by >= $0.01.'
        );
    }

    /**
     * In LEGACY mode (formatting disabled OR tax not included in catalog prices),
     * convertQuoteItems() must behave identically to the old code: unit_amount uses
     * the plain item price and no rounding_adjustment item is added.
     *
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     * @magentoDbIsolation enabled
     */
    public function testConvertQuoteItemsDoesNotAddRoundingAdjustmentInLegacyMode(): void
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

        /** @var Config|\PHPUnit\Framework\MockObject\MockObject $config */
        $config = $this->createMock(Config::class);
        $config->method('isGatewayPriceFormattingEnabled')->willReturn(false); // LEGACY
        $config->method('isTaxIncludedInPrices')->willReturn(false);
        $config->method('getGatewayPriceFormat')->willReturn(GatewayPriceFormat::LEGACY_MODE);
        $config->method('isTaxIncludedInShipping')->willReturn(false);
        $config->method('isUseShippingNameAsFallback')->willReturn(false);
        $config->method('getPriceFormatLineItemTitle')->willReturn('Rounding');

        /** @var QuoteConverter $quoteConverter */
        $quoteConverter = $objectManager->create(QuoteConverter::class, ['config' => $config]);

        $result = $quoteConverter->convertQuoteItems($quote);
        $itemSkus = array_column($result['order_data']['items'] ?? [], 'sku');

        self::assertNotContains(
            'rounding_adjustment',
            $itemSkus,
            'No rounding_adjustment item should appear in LEGACY mode.'
        );
    }

    // ─── CHK-9535: shipping name as billing fallback ───────────────────────────

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
        $config->method('isGatewayPriceFormattingEnabled')->willReturn(false);
        $config->method('isTaxIncludedInPrices')->willReturn(false);
        $config->method('getPriceFormatLineItemTitle')->willReturn('Rounding');

        /** @var QuoteConverter $quoteConverter */
        $quoteConverter = $objectManager->create(QuoteConverter::class, ['config' => $config]);

        $result = $quoteConverter->convertCustomer($quote);

        self::assertSame('Jane', $result['order_data']['customer']['first_name']);
        self::assertSame('Doe', $result['order_data']['customer']['last_name']);
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

        $quote->getBillingAddress()->setFirstname(null)->setLastname(null);
        $quote->getShippingAddress()->setFirstname('Jane')->setLastname('Doe');

        /** @var Config|\PHPUnit\Framework\MockObject\MockObject $config */
        $config = $this->createMock(Config::class);
        $config->method('isUseShippingNameAsFallback')->willReturn(false);
        $config->method('isGatewayPriceFormattingEnabled')->willReturn(false);
        $config->method('isTaxIncludedInPrices')->willReturn(false);
        $config->method('getPriceFormatLineItemTitle')->willReturn('Rounding');

        /** @var QuoteConverter $quoteConverter */
        $quoteConverter = $objectManager->create(QuoteConverter::class, ['config' => $config]);

        $result = $quoteConverter->convertCustomer($quote);

        self::assertSame('noname', $result['order_data']['customer']['first_name']);
        self::assertSame('nolastname', $result['order_data']['customer']['last_name']);
    }
}
