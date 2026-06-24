<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Model\Order;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\Order\ResolvePublicOrderId;
use Magento\Checkout\Model\Session;
use Magento\Quote\Api\Data\CartExtensionInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class ResolvePublicOrderIdTest extends TestCase
{
    /**
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     */
    public function testPrefersSessionIdOverStaleExtensionIdWhenQuoteIsProcessed(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var MagentoQuoteBoldOrderRepositoryInterface $repository */
        $repository = $objectManager->create(MagentoQuoteBoldOrderRepositoryInterface::class);
        /** @var Session $checkoutSession */
        $checkoutSession = $objectManager->get(Session::class);
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);

        $quoteResource->load($quote, 'test_order_item_with_items', 'reserved_order_id');
        $quoteId = (string)$quote->getId();

        $repository->saveStateAt($quoteId);

        $checkoutSession->replaceQuote($quote);
        $checkoutSession->setBoldCheckoutData(['data' => ['public_order_id' => 'session-public-order-id']]);

        /** @var CartExtensionInterface $extensionAttributes */
        $extensionAttributes = $quote->getExtensionAttributes();
        $extensionAttributes->setBoldOrderId('stale-extension-id');

        /** @var ResolvePublicOrderId $resolvePublicOrderId */
        $resolvePublicOrderId = $objectManager->create(ResolvePublicOrderId::class);

        self::assertSame('session-public-order-id', $resolvePublicOrderId->execute($quote));
    }

    /**
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     */
    public function testReconcilesStaleExtensionIdToSessionForActiveQuote(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var MagentoQuoteBoldOrderRepositoryInterface $repository */
        $repository = $objectManager->create(MagentoQuoteBoldOrderRepositoryInterface::class);
        /** @var Session $checkoutSession */
        $checkoutSession = $objectManager->get(Session::class);
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);

        $quoteResource->load($quote, 'test_order_item_with_items', 'reserved_order_id');
        $quoteId = (string)$quote->getId();

        $checkoutSession->replaceQuote($quote);
        $checkoutSession->setBoldCheckoutData(['data' => ['public_order_id' => 'session-public-order-id']]);

        /** @var CartExtensionInterface $extensionAttributes */
        $extensionAttributes = $quote->getExtensionAttributes();
        $extensionAttributes->setBoldOrderId('stale-extension-id');

        /** @var ResolvePublicOrderId $resolvePublicOrderId */
        $resolvePublicOrderId = $objectManager->create(ResolvePublicOrderId::class);

        self::assertSame('session-public-order-id', $resolvePublicOrderId->execute($quote));
        self::assertSame('session-public-order-id', $extensionAttributes->getBoldOrderId());
        self::assertSame(
            'session-public-order-id',
            $repository->getByQuoteId($quoteId)->getBoldOrderId()
        );
    }

    /**
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     */
    public function testUsesExtensionIdWhenSessionIsEmpty(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);

        $quoteResource->load($quote, 'test_order_item_with_items', 'reserved_order_id');

        /** @var CartExtensionInterface $extensionAttributes */
        $extensionAttributes = $quote->getExtensionAttributes();
        $extensionAttributes->setBoldOrderId('extension-only-id');

        /** @var ResolvePublicOrderId $resolvePublicOrderId */
        $resolvePublicOrderId = $objectManager->create(ResolvePublicOrderId::class);

        self::assertSame('extension-only-id', $resolvePublicOrderId->execute($quote));
    }

    /**
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     */
    public function testTreatsEmptyExtensionIdAsNullAndUsesSession(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var Session $checkoutSession */
        $checkoutSession = $objectManager->get(Session::class);
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var QuoteResource $quoteResource */
        $quoteResource = $objectManager->create(QuoteResource::class);

        $quoteResource->load($quote, 'test_order_item_with_items', 'reserved_order_id');

        $checkoutSession->replaceQuote($quote);
        $checkoutSession->setBoldCheckoutData(['data' => ['public_order_id' => 'session-public-order-id']]);

        /** @var CartExtensionInterface $extensionAttributes */
        $extensionAttributes = $quote->getExtensionAttributes();
        $extensionAttributes->setBoldOrderId('');

        /** @var ResolvePublicOrderId $resolvePublicOrderId */
        $resolvePublicOrderId = $objectManager->create(ResolvePublicOrderId::class);

        self::assertSame('session-public-order-id', $resolvePublicOrderId->execute($quote));
    }
}
