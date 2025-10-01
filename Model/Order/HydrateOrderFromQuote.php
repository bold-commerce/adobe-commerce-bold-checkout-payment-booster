<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Bold\CheckoutPaymentBooster\Model\Order\Address\Converter;
use Bold\CheckoutPaymentBooster\Model\Quote\GetCartLineItems;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Customer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\ToOrderAddress;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order\Address;

/**
 * Hydrate Bold order from Magento quote.
 */
class HydrateOrderFromQuote
{
    private const HYDRATE_ORDER_URL = 'checkout_sidekick/{{shopId}}/order/%s';
    private const EXPECTED_SEGMENTS = [
        'subtotal',
        'shipping',
        'discount',
        'tax',
        'grand_total',
    ];

    /**
     * @var BoldClient
     */
    private $client;

    /**
     * @var GetCartLineItems
     */
    private $getCartLineItems;

    /**
     * @var Converter
     */
    private $addressConverter;

    /**
     * @var ToOrderAddress
     */
    private $quoteToOrderAddressConverter;

    /** @var MagentoQuoteBoldOrderRepositoryInterface */
    private $magentoQuoteBoldOrderRepository;

    /**
     * @param BoldClient $client
     * @param GetCartLineItems $getCartLineItems
     * @param Converter $addressConverter
     * @param ToOrderAddress $quoteToOrderAddressConverter
     * @param MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository
     */
    public function __construct(
        BoldClient $client,
        GetCartLineItems $getCartLineItems,
        Converter $addressConverter,
        ToOrderAddress $quoteToOrderAddressConverter,
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository
    ) {
        $this->client = $client;
        $this->getCartLineItems = $getCartLineItems;
        $this->addressConverter = $addressConverter;
        $this->quoteToOrderAddressConverter = $quoteToOrderAddressConverter;
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
    }

    /**
     * Hydrate and post Bold order from Magento quote.
     *
     * @param CartInterface&Quote $quote
     * @param string $publicOrderId
     * @return void
     * @throws LocalizedException
     */
    public function hydrate(CartInterface $quote, string $publicOrderId): void
    {
        $quote->collectTotals();
        $websiteId = (int)$quote->getStore()->getWebsiteId();
        /** @var OrderAddressInterface&Address $billingAddress */
        $billingAddress = $this->quoteToOrderAddressConverter->convert($quote->getBillingAddress());
        /** @var OrderAddressInterface&Address $shippingAddress */
        $shippingAddress = $quote->getIsVirtual()
            ? $billingAddress
            : $this->quoteToOrderAddressConverter->convert($quote->getShippingAddress());

        if ($quote->getIsVirtual()) {
            $totals = $quote->getBillingAddress()->getTotals();
        } else {
            $totals = $quote->getShippingAddress()->getTotals();
            $shippingDescription = $quote->getShippingAddress()->getShippingDescription();
        }

        [$fees, $discounts] = $this->getFeesAndDiscounts($totals);
        $discountTotal = array_reduce($discounts, function (?float $sum, $discountLine) {
            return $sum + $discountLine['value'];
        });

        $cartItems = $this->getCartLineItems->getItems($quote);
        $formattedCartItems = $this->formatCartItems($cartItems);
        $subtotal = $totals['subtotal']['value_excl_tax'] ?? $totals['subtotal']['value'];
        $shippingTotal = $totals['shipping']['value'] ?? 0;
        $shippingTotalNoTax = $totals['shipping']['value_excl_tax'] ?? $shippingTotal;
        $grandTotal = $totals['grand_total']['value_incl_tax'] ?? $totals['grand_total']['value'];
        $body = [
            'billing_address' => $this->addressConverter->convert($billingAddress),
            'shipping_address' => $this->addressConverter->convert($shippingAddress),
            'cart_items' => $formattedCartItems,
            'taxes' => $this->getTaxLines($totals['tax']['full_info']),
            'discounts' => $discounts,
            'fees' => $fees,
            'shipping_line' => [
                'rate_name' => $shippingDescription ?? '',
                'cost' => $this->convertToCents((float)$shippingTotalNoTax),
            ],
            'totals' => [
                'sub_total' => $this->convertToCents((float)$subtotal),
                'tax_total' => $this->convertToCents($totals['tax']['value']),
                'discount_total' => $discountTotal ?? 0,
                'shipping_total' => $this->convertToCents((float)$shippingTotalNoTax),
                'order_total' => $this->convertToCents((float)$grandTotal),
            ],
        ];
        /** @var CustomerInterface&Customer $customer */
        $customer = $quote->getCustomer();

        if ($customer->getId()) {
            $body['customer'] = [
                'platform_id' => (string)$quote->getCustomerId(),
                'first_name' => $quote->getCustomerFirstname(),
                'last_name' => $quote->getCustomerLastname(),
                'email_address' => $quote->getCustomerEmail(),
            ];
        } else {
            $body['customer'] = [
                'platform_id' => null,
                'first_name' => $billingAddress->getFirstname(),
                'last_name' => $billingAddress->getLastname(),
                'email_address' => $billingAddress->getEmail(),
            ];
        }

        $url = sprintf(self::HYDRATE_ORDER_URL, $publicOrderId);
        $hydrateResponse = $this->client->put($websiteId, $url, $body);

        if ($hydrateResponse->getStatus() !== 201) {
            throw new LocalizedException(__('Failed to hydrate order with id="%1"', $publicOrderId));
        }

        $this->magentoQuoteBoldOrderRepository->saveHydratedAt((string) $quote->getId());
    }

    /**
     * Converts a dollar amount to cents
     *
     * @param float|string $dollars
     * @return integer
     */
    private function convertToCents($dollars): int
    {
        return (int)round(floatval($dollars) * 100);
    }

    /**
     * Get formatted tax lines
     *
     * @param array{id: string|int, base_amount: float}[] $taxes
     * @return array{name: string, value: float}[]
     */
    private function getTaxLines(array $taxes): array
    {
        $taxLines = [];

        foreach ($taxes as $tax) {
            $taxLines[] = [
                'name' => (string)$tax['id'],
                'value' => $this->convertToCents($tax['base_amount']),
            ];
        }

        return $taxLines;
    }

    /**
     * Looks at total segments and makes unrecognized segments into fees and discounts
     *
     * @param array<string, array{code: string, value: float, title: string}> $totals
     * @return array{line_text?: string, description?: string, value: float}[][]
     */
    private function getFeesAndDiscounts(array $totals): array
    {
        $fees = [];
        $discounts = [];

        if (isset($totals['discount'])) {
            $discounts[] = [
                'line_text' => $totals['discount']['code'],
                'value' => abs($this->convertToCents($totals['discount']['value'])),
            ];
        }

        foreach ($totals as $segment) {
            if (in_array($segment['code'], self::EXPECTED_SEGMENTS) || !$segment['value']) {
                continue;
            }

            $description = $segment['title'] ?? ucfirst(str_replace('_', ' ', $segment['code']));

            if ($segment['value'] > 0) {
                $fees[] = [
                    'description' => $description,
                    'value' => $this->convertToCents($segment['value']),
                ];
            } else {
                $discounts[] = [
                    'line_text' => $description,
                    'value' => abs($this->convertToCents($segment['value'])),
                ];
            }
        }

        return [$fees, $discounts];
    }

    /**
     * @param mixed[] $cartItems
     * @return mixed[]
     */
    private function formatCartItems(array $cartItems): array
    {
        foreach ($cartItems as &$item) {
            $item['vendor'] = '';
            $item['weight'] = (int)ceil($item['weight']);
        }

        return $cartItems;
    }
}
