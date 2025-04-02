<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Service\DigitalWallets\MagentoQuote;

use Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote\Deactivator;
use Bold\CheckoutPaymentBooster\Test\Integration\_Stubs\Magento\Quote\Model\ResourceModel\Quote\CollectionStub
    as QuoteCollectionStub;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\Renderer\Composite;
use Magento\Framework\Phrase\Renderer\MessageFormatter;
use Magento\Framework\Phrase\Renderer\Placeholder;
use Magento\Framework\Phrase\RendererInterface;
use Magento\Framework\Translate;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\Quote\Model\ResourceModel\Quote\Collection as QuoteCollection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function class_exists;
use function count;

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

    /**
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/multiple_active_digital_wallets_quotes.php
     */
    public function testDeactivatesAllQuotesSuccessfully(): void
    {
        $this->fixTranslationRenderer();

        $arguments = [];
        $logger = null;

        if ($this->canPerformLoggingAssertions()) {
            $logger = new \ColinODell\PsrTestLogger\TestLogger();
            $arguments['logger'] = $logger;
        }

        $objectManager = Bootstrap::getObjectManager();
        /** @var Deactivator $quoteDeactivator */
        $quoteDeactivator = $objectManager->create(Deactivator::class, $arguments);
        /** @var QuoteCollection $quoteCollection */
        $quoteCollection = $objectManager->create(QuoteCollection::class);

        $deactivatedQuoteIds = $quoteDeactivator->deactivateAllQuotes();

        $quoteCollection->addFieldToFilter('is_digital_wallets', '1');
        $quoteCollection->addFieldToFilter('is_active', '1');
        $quoteCollection->load();

        self::assertSame(0, $quoteCollection->count());
        self::assertNotEmpty($deactivatedQuoteIds);
        self::assertSuccessfulQuoteDeactivationsWereLogged($logger, $deactivatedQuoteIds);
    }

    public function testDoesNotDeactivateAllQuotesSuccessfully(): void
    {
        $this->fixTranslationRenderer();

        $arguments = [];
        $logger = null;

        if ($this->canPerformLoggingAssertions()) {
            $logger = new \ColinODell\PsrTestLogger\TestLogger();
            $arguments['logger'] = $logger;
        }

        $quoteCollectionFactoryStub = $this->createStub(QuoteCollectionFactory::class);
        $arguments['quoteCollectionFactory'] = $quoteCollectionFactoryStub;
        $objectManager = Bootstrap::getObjectManager();
        $quoteCollectionStub = $objectManager->create(QuoteCollectionStub::class);
        /** @var CouldNotSaveException $couldNotSaveException */
        $couldNotSaveException = $objectManager->create(
            CouldNotSaveException::class,
            [
                'phrase' => __('Could not save quote')
            ]
        );
        $quotes = [];
        /** @var Deactivator $quoteDeactivator */
        $quoteDeactivator = $objectManager->create(Deactivator::class, $arguments);

        $quoteCollectionFactoryStub
            ->method('create')
            ->willReturn($quoteCollectionStub);

        for ($i = 0; $i < 5; $i++) {
            $quote = $this->createStub(Quote::class);

            $quote
                ->method('getId')
                ->willReturn($i);
            $quote
                ->method('save')
                ->willThrowException($couldNotSaveException);

            $quotes[$i] = $quote;
        }

        $quoteCollectionStub->setItems($quotes);

        $deactivatedQuoteIds = $quoteDeactivator->deactivateAllQuotes();

        self::assertEmpty($deactivatedQuoteIds);
        self::assertUnsuccessfulQuoteDeactivationsWereLogged($logger, $couldNotSaveException);
    }

    private function fixTranslationRenderer(): void
    {
        $translateStub = $this
            ->getMockBuilder(Translate::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getLocale'])
            ->getMock();
        $objectManager = Bootstrap::getObjectManager();
        /** @var RendererInterface $messageFormatter */
        $messageFormatter = $objectManager->create(
            MessageFormatter::class,
            [
                'translate' => $translateStub,
            ]
        );
        /** @var RendererInterface $renderer */
        $renderer = $objectManager->create(
            Composite::class,
            [
                'renderers' => [
                    $messageFormatter,
                    $objectManager->create(Placeholder::class)
                ]
            ]
        );

        $translateStub
            ->method('getLocale')
            ->willReturn('en_US');

        Phrase::setRenderer($renderer);
    }

    private function canPerformLoggingAssertions(): bool
    {
        return class_exists('\ColinODell\PsrTestLogger\TestLogger');
    }

    /**
     * @param \ColinODell\PsrTestLogger\TestLogger|null $testLogger
     * @param int[][] $deactivatedQuoteIdsByWebsite
     */
    private static function assertSuccessfulQuoteDeactivationsWereLogged(
        $testLogger,
        array $deactivatedQuoteIdsByWebsite
    ): void {
        if ($testLogger === null) {
            return;
        }

        self::assertFalse($testLogger->hasErrorRecords());

        foreach ($deactivatedQuoteIdsByWebsite as $websiteId => $deactivatedQuoteIds) {
            $message = sprintf(
                'Deactivated %d Digital Wallets quotes with IDs "%s" in website "%s".',
                count($deactivatedQuoteIds),
                implode(', ', $deactivatedQuoteIds),
                $websiteId
            );

            self::assertTrue($testLogger->hasInfo($message));
        }
    }

    /**
     * @param \ColinODell\PsrTestLogger\TestLogger|null $testLogger
     */
    private static function assertUnsuccessfulQuoteDeactivationsWereLogged(
        $testLogger,
        CouldNotSaveException $couldNotSaveException
    ): void {
        if ($testLogger === null) {
            return;
        }

        self::assertFalse($testLogger->hasInfoRecords());

        for ($i = 0; $i < 5; $i++) {
            self::assertTrue(
                $testLogger->hasError(
                    [
                        'message' => "Could not deactivate Digital Wallets quote with ID \"$i\".",
                        'context' => [
                            'exception' => $couldNotSaveException
                        ]
                    ]
                )
            );
        }
    }
}
