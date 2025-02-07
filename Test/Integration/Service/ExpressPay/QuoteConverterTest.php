<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Service\ExpressPay;

use Bold\CheckoutPaymentBooster\Service\ExpressPay\QuoteConverter;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Quote\Api\CartRepositoryInterface;
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
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        parent::setUp();
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture current_store sales/custom_order_fees/custom_fees [{"code":"test_fee_0","title":"Test Fee","value":"4.00"},{"code":"test_fee_1","title":"Another Fee","value":"1.00"}]
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     */
    public function testConvertFullQuoteWithCustomFees(): void
    {
        $componentRegistrar = $this->objectManager->get(\Magento\Framework\Component\ComponentRegistrar::class);
        if ($componentRegistrar->getPath('module', 'JosephLeedy_CustomFees') === null) {
            $this->markTestSkipped('There is no custom fees module installed.');
        }
        $searchCriteria = $this->objectManager->create(SearchCriteriaBuilder::class)
            ->addFilter('reserved_order_id', 'test_order_1')
            ->create();
        $quotes = $this->objectManager->create(CartRepositoryInterface::class)
            ->getList($searchCriteria)
            ->getItems();
        /** @var Quote $quote */
        $quote = reset($quotes) ?: $this->objectManager->create(Quote::class);
        $quoteConverter = $this->objectManager->create(QuoteConverter::class);

        $expectedConvertedQuoteData = [
            'gateway_id' => 'a31a8fd6-a9e2-4c68-a834-54567bfeb4b7',
            'order_data' => [
                'locale' => 'en-US',
                'customer' => [
                    'first_name' => 'John',
                    'last_name' => 'Smith',
                    'email' => 'customer@example.com',
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
                        'currency_code' => 'USD',
                        'value' => '10.00',
                    ],
                ],
                'shipping_options' => [
                    [
                        'id' => 'flatrate_flatrate',
                        'label' => 'Flat Rate - Fixed',
                        'type' => 'SHIPPING',
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => '10.00',
                        ],
                    ],
                ],
                'items' => [
                    [
                        'name' => 'Simple Product',
                        'sku' => 'simple',
                        'unit_amount' => [
                            'currency_code' => 'USD',
                            'value' => '10.00',
                        ],
                        'quantity' => 2,
                        'is_shipping_required' => true,
                    ],
                    [
                        'name' => 'Test Fee',
                        'sku' => 'test_fee_0',
                        'unit_amount' => [
                            'currency_code' => 'USD',
                            'value' => '4.00',
                        ],
                        'quantity' => 1,
                        'is_shipping_required' => false,
                    ],
                    [
                        'name' => 'Another Fee',
                        'sku' => 'test_fee_1',
                        'unit_amount' => [
                            'currency_code' => 'USD',
                            'value' => '1.00',
                        ],
                        'quantity' => 1,
                        'is_shipping_required' => false,
                    ],
                ],
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => '31.50',
                ],
                'item_total' => [
                    'currency_code' => 'USD',
                    'value' => '25.00',
                ],
                'tax_total' => [
                    'currency_code' => 'USD',
                    'value' => '1.50',
                ],
                'discount' => [
                    'currency_code' => 'USD',
                    'value' => '5.00',
                ],
            ],
        ];
        $actualConvertedQuoteData = $quoteConverter->convertFullQuote($quote, 'a31a8fd6-a9e2-4c68-a834-54567bfeb4b7');

        self::assertEquals($expectedConvertedQuoteData, $actualConvertedQuoteData);
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     */
    public function testConvertNonVirtualQuote(): void
    {
        $searchCriteria = $this->objectManager->create(SearchCriteriaBuilder::class)
            ->addFilter('reserved_order_id', 'test_order_1')
            ->create();
        $quotes = $this->objectManager->create(CartRepositoryInterface::class)
            ->getList($searchCriteria)
            ->getItems();
        /** @var Quote $quote */
        $quote = reset($quotes) ?: $this->objectManager->create(Quote::class);
        $quoteConverter = $this->objectManager->create(QuoteConverter::class);

        $expectedConvertedQuoteData = [
            'gateway_id' => 'a31a8fd6-a9e2-4c68-a834-54567bfeb4b7',
            'order_data' => [
                'locale' => 'en-US',
                'customer' => [
                    'first_name' => 'John',
                    'last_name' => 'Smith',
                    'email' => 'customer@example.com',
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
                        'currency_code' => 'USD',
                        'value' => '10.00',
                    ],
                ],
                'shipping_options' => [
                    [
                        'id' => 'flatrate_flatrate',
                        'label' => 'Flat Rate - Fixed',
                        'type' => 'SHIPPING',
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => '10.00',
                        ],
                    ],
                ],
                'items' => [
                    [
                        'name' => 'Simple Product',
                        'sku' => 'simple',
                        'unit_amount' => [
                            'currency_code' => 'USD',
                            'value' => '10.00',
                        ],
                        'quantity' => 2,
                        'is_shipping_required' => true,
                    ],
                ],
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => '26.50',
                ],
                'item_total' => [
                    'currency_code' => 'USD',
                    'value' => '20.00',
                ],
                'tax_total' => [
                    'currency_code' => 'USD',
                    'value' => '1.50',
                ],
                'discount' => [
                    'currency_code' => 'USD',
                    'value' => '5.00',
                ],
            ],
        ];
        $actualConvertedQuoteData = $quoteConverter->convertFullQuote($quote, 'a31a8fd6-a9e2-4c68-a834-54567bfeb4b7');

        self::assertEquals($expectedConvertedQuoteData, $actualConvertedQuoteData);
    }

    /**
     * Check if the quote is converted correctly when the base currency is not the same as the quote currency.
     *
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     * @magentoConfigFixture current_store currency/options/base USD
     * @magentoConfigFixture current_store currency/options/default USD
     * @magentoConfigFixture current_store currency/options/allow USD,CNY
     * @magentoDataFixture Magento/Directory/_files/usd_cny_rate.php
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_non_base_currency.php
     * @return void
     */
    public function testNonBaseCurrencyQuote(): void
    {
        $quoteConverter = $this->objectManager->create(QuoteConverter::class);
        $searchCriteria = $this->objectManager->create(SearchCriteriaBuilder::class)
            ->addFilter('reserved_order_id', 'test_order_1')
            ->create();
        $searchResult = $this->objectManager->create(CartRepositoryInterface::class)
            ->getList($searchCriteria)
            ->getItems();
        /** @var Quote $quote */
        $quote = reset($searchResult);
        $convertedQuote = $quoteConverter->convertFullQuote($quote, 'test-gateway-id');
        $expectedResult = [
            'gateway_id' => 'test-gateway-id',
            'order_data' => [
                'locale' => 'en-US',
                'customer' => [
                    'first_name' => 'John',
                    'last_name' => 'Smith',
                    'email' => 'customer@example.com',
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
                        'currency_code' => 'CNY',
                        'value' => '70.00',
                    ],
                ],
                'shipping_options' => [
                    [
                        'id' => 'flatrate_flatrate',
                        'label' => 'Flat Rate - Fixed',
                        'type' => 'SHIPPING',
                        'amount' => [
                            'currency_code' => 'CNY',
                            'value' => '70.00',
                        ],
                    ],
                ],
                'items' => [
                    [
                        'name' => 'Simple Product',
                        'sku' => 'simple',
                        'unit_amount' => [
                            'currency_code' => 'CNY',
                            'value' => '70.00',
                        ],
                        'quantity' => 2,
                        'is_shipping_required' => true,
                    ],
                ],
                'amount' => [
                    'currency_code' => 'CNY',
                    'value' => '185.50',
                ],
                'item_total' => [
                    'currency_code' => 'CNY',
                    'value' => '140.00',
                ],
                'tax_total' => [
                    'currency_code' => 'CNY',
                    'value' => '10.50',
                ],
                'discount' => [
                    'currency_code' => 'CNY',
                    'value' => '35.00',
                ],
            ],
        ];
        self::assertEquals($expectedResult, $convertedQuote);
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     * @magentoDataFixture Magento/ConfigurableProduct/_files/tax_rule.php
     * @magentoDataFixture Magento/SalesRule/_files/cart_rule_with_coupon_5_off_no_condition.php
     * @magentoDataFixture Magento/Checkout/_files/quote_with_virtual_product_saved.php
     */
    public function testConvertFullQuoteConvertsVirtualQuote(): void
    {
        $searchCriteria = $this->objectManager->create(SearchCriteriaBuilder::class)
            ->addFilter('reserved_order_id', 'test_order_with_virtual_product_without_address')
            ->create();
        /** @var CartRepositoryInterface $cartRepository */
        $cartRepository = $this->objectManager->create(CartRepositoryInterface::class);
        $quotes = $cartRepository->getList($searchCriteria)->getItems();
        /** @var Quote $quote */
        $quote = reset($quotes) ?: $this->objectManager->create(Quote::class);
        /** @var Item[] $quoteItems */
        $quoteItems = $quote->getAllItems();
        /** @var Registry $registry */
        $registry = $this->objectManager->get(Registry::class);
        /** @var Rule $taxRule */
        $taxRule = $registry->registry('_fixture/Magento_Tax_Model_Calculation_Rule');
        $quoteConverter = $this->objectManager->create(QuoteConverter::class);

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
                ],
                'items' => [
                    [
                        'name' => 'Virtual Product',
                        'sku' => 'virtual-product',
                        'unit_amount' => [
                            'currency_code' => 'USD',
                            'value' => '10.00',
                        ],
                        'quantity' => 1,
                        'is_shipping_required' => false,
                    ],
                ],
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => '5.50',
                ],
                'item_total' => [
                    'currency_code' => 'USD',
                    'value' => '10.00',
                ],
                'tax_total' => [
                    'currency_code' => 'USD',
                    'value' => '0.50',
                ],
                'discount' => [
                    'currency_code' => 'USD',
                    'value' => '5.00',
                ],
            ],
        ];
        $actualConvertedQuoteData = array_filter(
            $quoteConverter->convertFullQuote(
                $quote,
                'a31a8fd6-a9e2-4c68-a834-54567bfeb4b7'
            )
        );

        self::assertEquals($expectedConvertedQuoteData, $actualConvertedQuoteData);
    }

    public function testDoesNotConvertShippingInformationIfAddressIsNotSet(): void
    {
        /** @var ObjectManagerInterface $this ->objectManager */

        /** @var Quote $quote */
        $quote = $this->objectManager->create(Quote::class);
        /** @var QuoteConverter $quoteConverter */
        $quoteConverter = $this->objectManager->create(QuoteConverter::class);

        self::assertEmpty($quoteConverter->convertShippingInformation($quote));
    }

    public function testDoesNotConvertShippingInformationIfAddressRateHasError(): void
    {
        /** @var ObjectManagerInterface $this ->objectManager */

        /** @var Quote $quote */
        $quote = $this->objectManager->create(Quote::class);
        /** @var QuoteConverter $quoteConverter */
        $quoteConverter = $this->objectManager->create(QuoteConverter::class);
        /** @var Rate $rate */
        $rate = $this->objectManager->create(Rate::class);
        /** @var AddressRateResultError $erroneousShippingRateResult */
        $erroneousShippingRateResult = $this->objectManager->create(AddressRateResultError::class);
        $shippingAddress = $quote->getShippingAddress();

        $shippingAddress->setId(42);
        $shippingAddress->addShippingRate($rate);

        $rate->importShippingRate($erroneousShippingRateResult);

        self::assertEmpty($quoteConverter->convertShippingInformation($quote)['order_data']);
    }

    public function testDoesNotConvertCustomerIfBillingAddressIsNotSet(): void
    {
        /** @var ObjectManagerInterface $this ->objectManager */

        /** @var Quote $quote */
        $quote = $this->objectManager->create(Quote::class);
        /** @var QuoteConverter $quoteConverter */
        $quoteConverter = $this->objectManager->create(QuoteConverter::class);

        self::assertEmpty($quoteConverter->convertCustomer($quote));
    }
}
