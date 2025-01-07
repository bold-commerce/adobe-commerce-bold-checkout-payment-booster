<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\Quote;

use Bold\CheckoutPaymentBooster\Api\Quote\GetQuoteInterface;
use Magento\Checkout\Model\Session;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;

/**
 * Get Current Quote ID from Magento quote.
 */
class GetQuote implements GetQuoteInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var QuoteIdToMaskedQuoteId
     */
    private $quoteIdToMaskedQuoteId;

    public function __construct(
        Session $checkoutSession,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
    }

    /**
     * Gets Current Session Quote ID
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getQuoteId()
    {
        $quoteId = (int)$this->checkoutSession->getQuote()->getId();
        $quoteIdMask = $this->quoteIdToMaskedQuoteId->execute($quoteId);

        return $quoteIdMask;
    }
}
