<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\ExpressPay;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Rate;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\ScopeInterface;

use function array_filter;
use function array_map;
use function array_merge;
use function array_merge_recursive;
use function array_sum;
use function array_values;
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

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @return array<string, string|array<string, array<string, string|float|array<string, string|float>>>>
     */
    public function convertFullQuote(Quote $quote, string $gatewayId): array
    {
        $quote->collectTotals();
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
    private function convertGatewayIdentifier(string $gatewayId): array
    {
        return [
            'gateway_id' => $gatewayId,
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function convertLocale(Quote $quote): array
    {
        /** @var string|null $locale */
        $locale = $this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_STORES,
            $quote->getStoreId()
        );

        return [
            'order_data' => [
                'locale' => str_replace('_', '-', $locale ?? ''),
            ],
        ];
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private function convertCustomer(Quote $quote): array
    {
        $billingAddress = $quote->getBillingAddress();

        if ($billingAddress->getId() === null) {
            return [];
        }

        return [
            'order_data' => [
                'customer' => [
                    'first_name' => $billingAddress->getFirstname() ?? '',
                    'last_name' => $billingAddress->getLastname() ?? '',
                    'email' => $billingAddress->getEmail() ?? '',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, array<array<string, array<string, string>|string>|string>>>
     */
    private function convertShippingInformation(Quote $quote, bool $includeAddress = true): array
    {
        $shippingAddress = $quote->getShippingAddress();

        if ($quote->getIsVirtual() || $shippingAddress->getId() === null) {
            return [];
        }

        $currencyCode = $quote->getCurrency() !== null ? $quote->getCurrency()->getQuoteCurrencyCode() : '';

        $shippingAddress->setCollectShippingRates(true);
        $shippingAddress->collectShippingRates();

        $usedRateCodes = [];
        /** @var Rate[] $shippingRates */
        $shippingRates = array_values(array_filter(
            $shippingAddress->getShippingRatesCollection()->getItems(),
            // @phpstan-ignore argument.type
            static function (Rate $rate) use (&$usedRateCodes): bool {
                if (in_array($rate->getCode(), $usedRateCodes)) {
                    return false;
                }

                $usedRateCodes[] = $rate->getCode();

                return true;
            }
        )); // Work-around for Magento bug causing duplicated shipping rates

        $convertedQuote = [
            'order_data' => [
                'shipping_options' => array_map(
                    static function (Rate $rate) use ($currencyCode): array {
                        return [
                            'id' => $rate->getCode(),
                            'label' => trim("{$rate->getCarrierTitle()} - {$rate->getMethodTitle()}", ' -'),
                            'type' => 'SHIPPING',
                            'amount' => [
                                'currency_code' => $currencyCode ?? '',
                                'value' => number_format((float)$rate->getPrice(), 2),
                            ],
                        ];
                    },
                    $shippingRates
                ),
            ],
        ];

        $hasRequiredAddressData = ($shippingAddress->getCity() && $shippingAddress->getCountryId());

        if ($includeAddress && $hasRequiredAddressData) {
            $convertedQuote['order_data']['shipping_address'] = [
                'address_line_1' => $shippingAddress->getStreet()[0] ?? '',
                'address_line_2' => $shippingAddress->getStreet()[1] ?? '',
                'city' => $shippingAddress->getCity() ?? '',
                'country_code' => $shippingAddress->getCountryId() ?? '',
                'postal_code' => $shippingAddress->getPostcode() ?? '',
                'state' => $shippingAddress->getRegion() ?? '',
            ];
        }

        if ($hasRequiredAddressData && $shippingAddress->hasShippingMethod()) { // @phpstan-ignore method.notFound
            $convertedQuote['order_data']['selected_shipping_option'] = [
                'id' => $shippingAddress->getShippingMethod(),
                'label' => $shippingAddress->getShippingDescription() ?? $shippingAddress->getShippingMethod(),
                'type' => 'SHIPPING',
                'amount' => [
                    'currency_code' => $currencyCode ?? '',
                    'value' => number_format((float)$shippingAddress->getShippingAmount(), 2),
                ],
            ];
        }

        return $convertedQuote;
    }

    /**
     * @return array<string, array<string, array<array<string, array<string, string>|bool|int|string>|string>>>
     */
    private function convertQuoteItems(Quote $quote): array
    {
        $quoteItems = $quote->getItems();

        if ($quoteItems === null) {
            return [];
        }

        $currencyCode = $quote->getCurrency() !== null ? $quote->getCurrency()->getQuoteCurrencyCode() : '';
        $convertedQuote = [
            'order_data' => [
                'items' => array_map(
                    static function (CartItemInterface $cartItem) use ($currencyCode): array {
                        return [
                            'name' => $cartItem->getName() ?? '',
                            'sku' => $cartItem->getSku() ?? '',
                            'unit_amount' => [
                                'currency_code' => $currencyCode ?? '',
                                'value' => number_format((float)$cartItem->getPrice(), 2),
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
                    $quoteItems
                ),
                'item_total' => [
                    'currency_code' => $currencyCode ?? '',
                    'value' => number_format(
                        array_sum(
                            array_map(
                                static function (CartItemInterface $cartItem) {
                                    return $cartItem->getPrice() * $cartItem->getQty();
                                },
                                $quoteItems
                            )
                        ),
                        2
                    ),
                ],
            ],
        ];

        $this->convertCustomTotals($quote, $convertedQuote);

        return $convertedQuote;
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private function convertTotal(Quote $quote): array
    {
        $currencyCode = $quote->getCurrency() !== null ? $quote->getCurrency()->getQuoteCurrencyCode() : '';
        return [
            'order_data' => [
                'amount' => [
                    'currency_code' => $currencyCode ?? '',
                    'value' => number_format((float)$quote->getGrandTotal(), 2),
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private function convertTaxes(Quote $quote): array
    {
        $currencyCode = $quote->getCurrency() !== null ? $quote->getCurrency()->getQuoteCurrencyCode() : '';
        $convertedQuote = [
            'order_data' => [
                'tax_total' => [
                    'currency_code' => $currencyCode ?? '',
                    'value' => '',
                ],
            ],
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
                (float)($quote->getShippingAddress()->getTaxAmount() ?? 0.00),
                2
            );
        }

        return $convertedQuote;
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private function convertDiscount(Quote $quote): array
    {
        $currencyCode = $quote->getCurrency() !== null ? $quote->getCurrency()->getQuoteCurrencyCode() : '';

        return [
            'order_data' => [
                'discount' => [
                    'currency_code' => $currencyCode ?? '',
                    'value' => number_format((float)($quote->getSubtotal() - $quote->getSubtotalWithDiscount()), 2),
                ],
            ],
        ];
    }

    /**
     * @phpcs:disable Generic.Files.LineLength.TooLong
     * @param array<string, array<string, array<array<string, array<string, string>|bool|int|string>|string>>> $convertedQuote
     * @phpcs:enable Generic.Files.LineLength.TooLong
     */
    private function convertCustomTotals(Quote $quote, array &$convertedQuote): void
    {
        $currencyCode = $quote->getCurrency() !== null ? $quote->getCurrency()->getQuoteCurrencyCode() : '';
        $excludedTotals = ['subtotal', 'shipping', 'tax', 'grand_total'];
        $customTotals = array_filter(
            $quote->getTotals(),
            static function (Total $total) use ($excludedTotals): bool {
                return !in_array($total->getCode(), $excludedTotals);
            }
        );

        if (count($customTotals) === 0) {
            return;
        }

        $customTotalsValue = 0;
        $totalItems = array_filter(
            array_map(
                static function (Total $total) use ($currencyCode, &$customTotalsValue): ?array {
                    /** @var string|null $name */
                    $name = $total->getData('title') ?? '';
                    /** @var float|string|null $value */
                    $value = $total->getData('value') ?? 0;

                    if ((float)$value === 0.00) {
                        return null;
                    }

                    $customTotalsValue += (float)$value;

                    return [
                        'name' => $name,
                        'sku' => $total->getCode() ?? '',
                        'unit_amount' => [
                            'currency_code' => $currencyCode ?? '',
                            'value' => number_format((float)$value, 2),
                        ],
                        'quantity' => 1,
                        'is_shipping_required' => false,
                    ];
                },
                array_values($customTotals)
            )
        );

        if ($customTotalsValue === 0) {
            return;
        }

        $convertedQuote['order_data']['items'] = array_merge($convertedQuote['order_data']['items'], $totalItems);
        $convertedQuote['order_data']['item_total']['value'] = number_format(
            ((float)$convertedQuote['order_data']['item_total']['value']) + $customTotalsValue,
            2
        );
    }
}
