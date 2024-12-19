<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Config;

/**
 * Hydrate Bold order from Magento quote.
 */
interface GetCheckoutConfigInterface
{
    /**
     * Gets Current Session Quote ID
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCheckoutConfig();
}
