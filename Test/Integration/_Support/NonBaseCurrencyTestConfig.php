<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\_Support;

/**
 * Shared currency constants for multi-currency integration tests.
 */
class NonBaseCurrencyTestConfig
{
    public const BASE_CURRENCY = 'USD';

    /**
     * @var list<string>
     */
    public const NON_BASE_DISPLAY_CURRENCIES = ['EUR', 'GBP'];
}
