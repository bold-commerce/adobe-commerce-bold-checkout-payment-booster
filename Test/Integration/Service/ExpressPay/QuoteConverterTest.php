<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Service\ExpressPay;

use Bold\CheckoutPaymentBooster\Service\ExpressPay\QuoteConverter;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
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
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
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
        $quoteConverter = $objectManager->create(QuoteConverter::class);

        $expectedConvertedQuoteData = [
            'gateway_id' => 'a31a8fd6-a9e2-4c68-a834-54567bfeb4b7',
            'order_data' => [
                'locale' => 'en-US',
                'customer' => [
                    'first_name' => 'John',
                    'last_name' => 'Smith',
                    'email' => 'customer@example.com'
                ],
                'shipping_address' => [
                    'address_line_1' => 'Green str, 67',
                    'address_line_2' => '',
                    'city' => 'CityM',
                    'country_code' => 'US',
                    'postal_code' => '75477',
                    'state' => 'Alabama'
                ],
                'selected_shipping_option' => [
                    'id' => 'flatrate_flatrate',
                    'label' => 'Flat Rate - Fixed',
                    'type' => 'SHIPPING',
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => '10.00'
                    ]
                ],
                'shipping_options' => [
                    [
                        'id' => 'flatrate_flatrate',
                        'label' => 'Flat Rate - Fixed',
                        'type' => 'SHIPPING',
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => '10.00'
                        ]
                    ]
                ],
                'items' => [
                    [
                        'name' => 'Simple Product',
                        'sku' => 'simple',
                        'unit_amount' => [
                            'currency_code' => 'USD',
                            'value' => '10.00'
                        ],
                        'quantity' => 2,
                        'is_shipping_required' => true
                    ]
                ],
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => '26.50'
                ],
                'item_total' => [
                    'currency_code' => 'USD',
                    'value' => '20.00'
                ],
                'tax_total' => [
                    'currency_code' => 'USD',
                    'value' => '1.50'
                ],
                'discount' => [
                    'currency_code' => 'USD',
                    'value' => '5.00'
                ]
            ]
        ];
        $actualConvertedQuoteData = $quoteConverter->convertFullQuote($quote, 'a31a8fd6-a9e2-4c68-a834-54567bfeb4b7');

        self::assertEquals($expectedConvertedQuoteData, $actualConvertedQuoteData);
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
                    'email' => 'customer@example.com'
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
}
