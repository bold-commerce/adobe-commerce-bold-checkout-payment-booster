<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\ExpressPay;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Rate;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\ScopeInterface;

use function array_map;
use function array_merge_recursive;
use function array_sum;
use function ceil;
use function in_array;
use function number_format;
use function str_replace;
use function trim;

/**
 * Translates Magento Quote data into Bold Checkout Express Pay Order data
 *
 * @api
 */
class QuoteConverter
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return array<string, string|array<string, array<string, string|float|array<string, string|float>>>>
     */
    public function convertFullQuote(Quote $quote, string $gatewayId): array
    {
        return array_merge_recursive(
            $this->convertGatewayIdentifier($gatewayId),
            $this->convertLocale($quote),
            $this->convertCustomer($quote),
            $this->convertShippingInformation($quote),
            $this->convertQuoteItems($quote),
            $this->convertTotal($quote),
            $this->convertTaxes($quote),
            $this->convertDiscount($quote)
        );
    }

    /**
     * @return array<string, string>
     */
    public function convertGatewayIdentifier(string $gatewayId): array
    {
        return [
            'gateway_id' => $gatewayId
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function convertLocale(Quote $quote): array
    {
        /** @var string|null $locale */
        $locale = $this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_STORES,
            $quote->getStoreId()
        );

        return [
            'order_data' => [
                'locale' => str_replace('_', '-', $locale ?? '')
            ]
        ];
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    public function convertCustomer(Quote $quote): array
    {
        $billingAddress = $quote->getBillingAddress();

        return [
            'order_data' => [
                'customer' => [
                    'first_name' => $billingAddress->getFirstname() ?? '',
                    'last_name' => $billingAddress->getLastname() ?? '',
                    'email' => $billingAddress->getEmail() ?? ''
                ]
            ]
        ];
    }

    /**
     * @return array<string, array<string, array<array<string, array<string, string>|string>|string>>>
     */
    public function convertShippingInformation(Quote $quote): array
    {
        if ($quote->getIsVirtual()) {
            return [];
        }

        $currencyCode = $quote->getCurrency() !== null ? $quote->getCurrency()->getQuoteCurrencyCode() : '';
        $shippingAddress = $quote->getShippingAddress();

        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();

        /** @var Rate[] $shippingRates */
        $shippingRates = $shippingAddress->getShippingRatesCollection()->getItems();

        return [
            'order_data' => [
                'shipping_address' => [
                    'address_line_1' => $shippingAddress->getStreet()[0] ?? '',
                    'address_line_2' => $shippingAddress->getStreet()[1] ?? '',
                    'city' => $shippingAddress->getCity() ?? '',
                    'country_code' => $shippingAddress->getCountryId() ?? '',
                    'postal_code' => $shippingAddress->getPostcode() ?? '',
                    'state' => $shippingAddress->getRegion() ?? ''
                ],
                'selected_shipping_option' => [
                    'label' => $shippingAddress->getShippingDescription(),
                    'type' => 'SHIPPING',
                    'amount' => [
                        'currency_code' => $currencyCode ?? '',
                        'value' => number_format((float)$shippingAddress->getShippingAmount(), 2)
                    ],
                ],
                'shipping_options' => array_map(
                    static function (Rate $rate) use ($currencyCode): array {
                        return [
                            'label' => trim("{$rate->getCarrierTitle()} - {$rate->getMethodTitle()}", ' -'),
                            'type' => 'SHIPPING',
                            'amount' => [
                                'currency_code' => $currencyCode ?? '',
                                'value' => number_format((float)$rate->getPrice(), 2)
                            ]
                        ];
                    },
                    $shippingRates
                )
            ]
        ];
    }

    /**
     * @return array<string, array<string, array<array<string, array<string, string>|bool|int|string>>>>
     */
    public function convertQuoteItems(Quote $quote): array
    {
        if ($quote->getItems() === null) {
            return [];
        }

        $currencyCode = $quote->getCurrency() !== null ? $quote->getCurrency()->getQuoteCurrencyCode() : '';

        return [
            'order_data' => [
                'items' => array_map(
                    static function (CartItemInterface $cartItem) use ($currencyCode): array {
                        return [
                            'name' => $cartItem->getName() ?? '',
                            'sku' => $cartItem->getSku() ?? '',
                            'unit_amount' => [
                                'currency_code' => $currencyCode ?? '',
                                'value' => number_format((float)$cartItem->getPrice(), 2)
                            ],
                            'quantity' => (int)(ceil($cartItem->getQty()) ?: $cartItem->getQty()),
                            'is_shipping_required' => !in_array(
                                $cartItem->getProductType(),
                                [
                                    'virtual',
                                    'downloadable',
                                    // TODO: add virtual gift cards to this list (Adobe Commerce only)
                                ],
                                true
                            ),
                        ];
                    },
                    $quote->getItems()
                )
            ]
        ];
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    public function convertTotal(Quote $quote): array
    {
        $currencyCode = $quote->getCurrency() !== null ? $quote->getCurrency()->getQuoteCurrencyCode() : '';

        $quote->collectTotals(); // Ensure that we have the correct grand total for the quote

        return [
            'order_data' => [
                'amount' => [
                    'currency_code' => $currencyCode ?? '',
                    'value' => number_format((float)$quote->getGrandTotal(), 2)
                ]
            ]
        ];
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    public function convertTaxes(Quote $quote): array
    {
        $currencyCode = $quote->getCurrency() !== null ? $quote->getCurrency()->getQuoteCurrencyCode() : '';
        $convertedQuote = [
            'order_data' => [
                'tax_total' => [
                    'currency_code' => $currencyCode ?? '',
                    'value' => ''
                ]
            ]
        ];

        if ($quote->getIsVirtual()) {
            /** @var Item[] $items */
            $items = $quote->getItems();
            $convertedQuote['order_data']['tax_total']['value'] = number_format(
                array_sum(
                    array_map(
                        static function (Item $item): float {
                            return $item->getTaxAmount() ?? 0.00;
                        },
                        $items
                    )
                ),
                2
            );
        } else {
            $convertedQuote['order_data']['tax_total']['value'] = number_format(
                (float)$quote->getShippingAddress()->getTaxAmount(),
                2
            );
        }

        return $convertedQuote;
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    public function convertDiscount(Quote $quote): array
    {
        $currencyCode = $quote->getCurrency() !== null ? $quote->getCurrency()->getQuoteCurrencyCode() : '';

        return [
            'order_data' => [
                'discount' => [
                    'currency_code' => $currencyCode ?? '',
                    'value' => number_format((float)($quote->getSubtotal() - $quote->getSubtotalWithDiscount()), 2)
                ]
            ]
        ];
    }
}
