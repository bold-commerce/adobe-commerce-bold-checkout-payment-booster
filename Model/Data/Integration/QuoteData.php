<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Data\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface;
use Magento\Framework\DataObject;

/**
 * Quote data model.
 */
class QuoteData extends DataObject implements QuoteDataInterface
{
    /**
     * @return string
     */
    public function getQuoteMaskId(): string
    {
        return $this->getData(self::QUOTE_MASK_ID);
    }

    /**
     * @return \Magento\Quote\Api\Data\CartInterface
     */
    public function getQuote(): \Magento\Quote\Api\Data\CartInterface
    {
        return $this->getData(self::QUOTE);
    }

    /**
     * @return \Magento\Quote\Api\Data\TotalsInterface
     */
    public function getTotals(): \Magento\Quote\Api\Data\TotalsInterface
    {
        return $this->getData(self::TOTALS);
    }

    /**
     * @param string $quoteMaskId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface
     */
    public function setQuoteMaskId(string $quoteMaskId): QuoteDataInterface
    {
        return $this->setData(self::QUOTE_MASK_ID, $quoteMaskId);
    }

    /**
     * @param \Magento\Quote\Api\Data\CartInterface $quote
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface
     */
    public function setQuote(\Magento\Quote\Api\Data\CartInterface $quote): QuoteDataInterface
    {
        return $this->setData(self::QUOTE, $quote);
    }

    /**
     * @param \Magento\Quote\Api\Data\TotalsInterface $totals
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface
     */
    public function setTotals(\Magento\Quote\Api\Data\TotalsInterface $totals): QuoteDataInterface
    {
        return $this->setData(self::TOTALS, $totals);
    }
}
