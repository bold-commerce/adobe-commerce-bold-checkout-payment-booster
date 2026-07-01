<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\_Support;

use PHPUnit\Framework\TestCase;

/**
 * Base integration test with multi-currency store configuration (USD base, EUR display).
 *
 * @magentoConfigFixture current_store currency/options/base USD
 * @magentoConfigFixture current_store currency/options/default USD
 * @magentoConfigFixture current_store currency/options/allow USD,EUR
 * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/multi_currency_store.php
 */
abstract class IntegrationTestCase extends TestCase
{
    protected const BASE_CURRENCY = NonBaseCurrencyTestConfig::BASE_CURRENCY;

    /**
     * @var list<string>
     */
    protected const NON_BASE_DISPLAY_CURRENCIES = NonBaseCurrencyTestConfig::NON_BASE_DISPLAY_CURRENCIES;
}
