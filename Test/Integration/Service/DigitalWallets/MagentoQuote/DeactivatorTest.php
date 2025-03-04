<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Service\DigitalWallets\MagentoQuote;

use Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote\Deactivator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class DeactivatorTest extends TestCase
{
    /**
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     */
    public function testDeactivatesQuoteSuccessfully(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var QuoteResourceModel $quoteResourceModel */
        $quoteResourceModel = $objectManager->create(QuoteResourceModel::class);
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var CartRepositoryInterface $cartRepository */
        $cartRepository = $objectManager->create(CartRepositoryInterface::class);
        /** @var Deactivator $quoteDeactivator */
        $quoteDeactivator = $objectManager->create(Deactivator::class);

        $quoteResourceModel->load($quote, 'test_order_item_with_items', 'reserved_order_id');

        $quote->setData('is_digital_wallets', true);

        $quoteResourceModel->save($quote);

        /** @var int|string $quoteId */
        $quoteId = $quote->getId() ?? 0;

        $quoteDeactivator->deactivateQuote((int)$quoteId);

        $deactivatedQuote = $cartRepository->get((int)$quoteId);

        self::assertFalse((bool)$deactivatedQuote->getIsActive());
    }

    public function testThrowsExceptionIfQuoteIdIsInvalid(): void
    {
        $this->expectExceptionMessage('Invalid quote identifier "42".');

        $objectManager = Bootstrap::getObjectManager();
        /** @var Deactivator $quoteDeactivator */
        $quoteDeactivator = $objectManager->create(Deactivator::class);

        $quoteDeactivator->deactivateQuote(42);
    }

    /**
     * @magentoDataFixture Magento/Checkout/_files/quote_with_items_saved.php
     */
    public function testThrowsExceptionIfQuoteIsNotDigitalWalletsQuote(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var QuoteResourceModel $quoteResourceModel */
        $quoteResourceModel = $objectManager->create(QuoteResourceModel::class);
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        /** @var Deactivator $quoteDeactivator */
        $quoteDeactivator = $objectManager->create(Deactivator::class);

        $quoteResourceModel->load($quote, 'test_order_item_with_items', 'reserved_order_id');

        /** @var int|string $quoteId */
        $quoteId = $quote->getId() ?? 0;

        $this->expectExceptionMessage(
            "Quote with identifier \"$quoteId\" is not a Digital Wallets quote."
        );

        $quoteDeactivator->deactivateQuote((int)$quoteId);
    }

    /**
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/magento_quote_bold_order.php
     */
    public function testThrowsExceptionIfDeactivatedQuoteCannotBeSaved(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var QuoteResourceModel $quoteResourceModel */
        $quoteResourceModel = $objectManager->create(QuoteResourceModel::class);
        /** @var Quote $quote */
        $quote = $objectManager->create(Quote::class);
        $quoteRepositoryStub = $this->createStub(CartRepositoryInterface::class);
        /** @var LocalizedException $localizedException */
        $localizedException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Unknown error')
            ]
        );
        /** @var Deactivator $quoteDeactivator */
        $quoteDeactivator = $objectManager->create(
            Deactivator::class,
            [
                'quoteRepository' => $quoteRepositoryStub,
            ]
        );

        $quoteResourceModel->load($quote, 'test_order_item_with_items', 'reserved_order_id');

        $quote->setData('is_digital_wallets', true);

        $quoteResourceModel->save($quote);

        $quoteRepositoryStub
            ->method('get')
            ->willReturn($quote);
        $quoteRepositoryStub
            ->method('save')
            ->willThrowException($localizedException);

        /** @var int|string $quoteId */
        $quoteId = $quote->getId() ?? 0;

        $this->expectExceptionMessage("Could not deactivate quote with identifier \"$quoteId\".");

        $quoteDeactivator->deactivateQuote((int)$quoteId);
    }
}
