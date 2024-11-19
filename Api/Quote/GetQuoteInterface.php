<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Quote;

/**
 * Hydrate Bold order from Magento quote.
 */
interface GetQuoteInterface
{
    /**
     * Gets Current Session Quote ID
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getQuoteId();
}
