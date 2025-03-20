<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Plugin\Checkout\Model;

use Bold\CheckoutPaymentBooster\Plugin\Checkout\Model\SessionPlugin;
use Bold\CheckoutPaymentBooster\Test\Integration\_Assertions\AssertPluginIsConfiguredCorrectly;
use Magento\Checkout\Model\Session;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class SessionPluginTest extends TestCase
{
    use AssertPluginIsConfiguredCorrectly;

    public function testIsConfiguredCorrectly(): void
    {
        self::assertPluginIsConfiguredCorrectly(
            'bold_booster_prevent_session_clear',
            SessionPlugin::class,
            Session::class
        );
    }

    /**
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address.php
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/digital_wallets_quote.php
     */
    public function testDoesNotClearSessionIfLastQuoteIsDigitalWalletsQuote(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $regularQuote */
        $regularQuote = $objectManager->create(Quote::class);
        /** @var Quote $digitalWalletsQuote */
        $digitalWalletsQuote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var Session $checkoutSession */
        $checkoutSession = $objectManager->get(Session::class);

        $quoteResource->load($regularQuote, 'test_order_1', 'reserved_order_id');
        $quoteResource->load($digitalWalletsQuote, 'digital_wallets_order_1', 'reserved_order_id');

        $checkoutSession->replaceQuote($regularQuote);
        $checkoutSession->setLastQuoteId($digitalWalletsQuote->getId());

        $checkoutSession->clearQuote();

        self::assertTrue($checkoutSession->hasQuote());
    }

    /**
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address.php
     */
    public function testClearsSessionIfLastQuoteIsNotDigitalWalletsQuote(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);
        /** @var Session $checkoutSession */
        $checkoutSession = $objectManager->get(Session::class);

        $quoteResource->load($quote, 'test_order_1', 'reserved_order_id');

        $checkoutSession->replaceQuote($quote);

        $checkoutSession->clearQuote();

        self::assertFalse($checkoutSession->hasQuote());
    }
}
