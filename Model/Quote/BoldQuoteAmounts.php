<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Quote;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Rate;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Item;

/**
 * Extracts base-currency amounts from Magento quotes for Bold API payloads.
 */
class BoldQuoteAmounts
{
    /**
     * Bold payments always use the store base currency.
     */
    public function getCurrencyCode(Quote $quote): string
    {
        $code = $quote->getBaseCurrencyCode();

        if ($code !== null && $code !== '') {
            return $code;
        }

        return (string)$quote->getStore()->getBaseCurrencyCode();
    }

    public function getGrandTotal(Quote $quote): float
    {
        return (float)$quote->getBaseGrandTotal();
    }

    public function getSubtotal(Quote $quote): float
    {
        return (float)$quote->getBaseSubtotal();
    }

    public function getTaxAmount(Quote $quote): float
    {
        if ($quote->getIsVirtual()) {
            $total = 0.0;

            foreach ($quote->getItems() ?? [] as $item) {
                if (!$item instanceof Item) {
                    continue;
                }

                $total += (float)($item->getBaseTaxAmount() ?? 0.00);
            }

            return $total;
        }

        return (float)($quote->getShippingAddress()->getBaseTaxAmount() ?? 0.00);
    }

    public function getShippingAmount(Quote $quote): float
    {
        if ($quote->getIsVirtual()) {
            return 0.0;
        }

        return (float)($quote->getShippingAddress()->getBaseShippingAmount() ?? 0.00);
    }

    public function getDiscountAmount(Quote $quote): float
    {
        $address = $quote->getIsVirtual()
            ? $quote->getBillingAddress()
            : $quote->getShippingAddress();

        return abs((float)($address->getBaseDiscountAmount() ?? 0.0));
    }

    public function getItemRowAmount(Item $item, bool $taxIncluded): float
    {
        if ($taxIncluded) {
            return (float)$item->getBaseRowTotalInclTax() - (float)($item->getBaseTaxAmount() ?? 0);
        }

        return (float)$item->getBaseRowTotal();
    }

    public function getItemUnitPrice(Item $item, bool $taxIncluded): float
    {
        $qty = (float)$item->getQty();

        if ($qty <= 0) {
            return 0.0;
        }

        return $this->getItemRowAmount($item, $taxIncluded) / $qty;
    }

    public function getShippingRateAmount(Rate $rate, Address $shippingAddress): float
    {
        if ($rate->getCode() === $shippingAddress->getShippingMethod()) {
            return (float)$shippingAddress->getBaseShippingAmount();
        }

        $baseToQuoteRate = (float)$shippingAddress->getQuote()->getBaseToQuoteRate();
        $price = (float)$rate->getPrice();

        if ($baseToQuoteRate > 0) {
            return $price / $baseToQuoteRate;
        }

        return $price;
    }

    /**
     * Base amount for extension/custom total segments. Returns null when only display currency is present.
     */
    public function getCustomTotalBaseAmount(Total $total): ?float
    {
        $code = (string)$total->getCode();
        $baseAmount = (float)$total->getBaseTotalAmount($code);
        $displayValue = (float)($total->getData('value') ?? 0);

        if ($baseAmount === 0.0 && $displayValue !== 0.0) {
            return null;
        }

        return $baseAmount;
    }

    /**
     * Base value for a fee/discount total segment used during order hydration.
     */
    public function getSegmentBaseValue(Total $segment, Quote $quote): ?float
    {
        $code = (string)$segment->getCode();
        $baseAmount = (float)$segment->getBaseTotalAmount($code);
        $displayValue = (float)($segment->getData('value') ?? 0);

        if ($baseAmount === 0.0 && $displayValue !== 0.0) {
            return null;
        }

        if ($baseAmount !== 0.0) {
            return $baseAmount;
        }

        if ($code === 'discount') {
            return $this->getDiscountAmount($quote);
        }

        return 0.0;
    }
}
