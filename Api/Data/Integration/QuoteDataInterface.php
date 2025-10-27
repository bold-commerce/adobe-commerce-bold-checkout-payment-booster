<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Integration;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\TotalsInterface;

/**
 * HTTP response data model interface.
 */
interface QuoteDataInterface
{
    CONST QUOTE_MASK_ID = 'quote_mask_id';
    CONST QUOTE = 'quote';
    CONST TOTALS = 'totals';
    CONST SHIPPING_METHODS = 'shipping_methods';

    /**
     * @return string
     */
    public function getQuoteMaskId(): string;

    /**
     * @return \Magento\Quote\Api\Data\CartInterface
     */
    public function getQuote(): \Magento\Quote\Api\Data\CartInterface;

    /**
     * @return \Magento\Quote\Api\Data\TotalsInterface
     */
    public function getTotals(): TotalsInterface;

    /**
     * @return \Magento\Quote\Api\Data\ShippingMethodInterface[]
     */
    public function getShippingMethods(): array;

    /**
     * @param string $quoteMaskId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface
     */
    public function setQuoteMaskId(string $quoteMaskId): \Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface;

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface
     */
    public function setQuote(\Magento\Quote\Api\Data\CartInterface $quote): \Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface;

    /**
     * @param \Magento\Quote\Api\Data\TotalsInterface $totals
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface
     */
    public function setTotals(\Magento\Quote\Api\Data\TotalsInterface $totals): \Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface;

    /**
     * @param \Magento\Quote\Api\Data\ShippingMethodInterface[] $shippingMethods
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface
     */
    public function setShippingMethods(array $shippingMethods): \Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface;
}
