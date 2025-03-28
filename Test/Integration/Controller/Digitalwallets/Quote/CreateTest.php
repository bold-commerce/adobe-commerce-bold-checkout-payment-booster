<?php

/** @noinspection PhpLanguageLevelInspection */

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Controller\Digitalwallets\Quote;

use Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote\Creator as MagentoQuoteCreator;
use Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote\TotalsRetriever;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

use function __;

class CreateTest extends AbstractController
{
    public function testCreatesQuoteSuccessfully(): void
    {
        $productRepositoryStub = $this->createStub(ProductRepositoryInterface::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var ProductInterface $product */
        $product = $objectManager->create(ProductInterface::class);
        $quoteRepositoryStub = $this->createStub(CartRepositoryInterface::class);
        /** @var CartInterface $quote */
        $quote = $objectManager->create(CartInterface::class);
        $magentoQuoteCreatorStub = $this->createStub(MagentoQuoteCreator::class);
        $totalsRetrieverStub = $this->createStub(TotalsRetriever::class);
        /** @var FormKey $formKey */
        $formKey = $objectManager->get(FormKey::class);
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        /** @var SerializerInterface $serializer */
        $serializer = $objectManager->create(SerializerInterface::class);

        $productRepositoryStub
            ->method('getById')
            ->willReturn($product);

        $quoteRepositoryStub
            ->method('get')
            ->willReturn($quote);

        $magentoQuoteCreatorStub
            ->method('createQuote')
            ->willReturn(
                [
                    'quote' => $quote,
                    'maskedId' => '648a1e8f8500478c92bd618a3a1171d76bd79a7128954aa3a99275284608eb5c'
                ]
            );

        $totalsRetrieverStub
            ->method('retrieveTotals')
            ->willReturn([]);

        $objectManager->configure(
            [
                ProductRepository::class => [
                    'shared' => true,
                ],
                QuoteRepository::class => [
                    'shared' => true,
                ],
                MagentoQuoteCreator::class => [
                    'shared' => true,
                ],
                TotalsRetriever::class => [
                    'shared' => true,
                ],
            ]
        );
        // @phpstan-ignore-next-line
        $objectManager->addSharedInstance($productRepositoryStub, ProductRepository::class);
        // @phpstan-ignore-next-line
        $objectManager->addSharedInstance($quoteRepositoryStub, QuoteRepository::class);
        // @phpstan-ignore-next-line
        $objectManager->addSharedInstance($magentoQuoteCreatorStub, MagentoQuoteCreator::class);
        // @phpstan-ignore-next-line
        $objectManager->addSharedInstance($totalsRetrieverStub, TotalsRetriever::class);

        $request
            ->setMethod(HttpRequest::METHOD_POST)
            ->setPostValue(
                [
                    'form_key' => $formKey->getFormKey(),
                    'product' => 42,
                ]
            );

        $this->dispatch('bold_booster/digitalwallets_quote/create');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        /** @var mixed[] $responseBody */
        $responseBody = $serializer->unserialize($response->getBody());

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($responseBody['success']);
        self::assertArrayHasKey('quoteData', $responseBody);
        self::assertArrayHasKey('quoteItemData', $responseBody);
        self::assertArrayHasKey('totalsData', $responseBody);
    }

    /**
     * @dataProvider incorrectProductParameterDataProvider
     */
    public function testReturnsErrorForIncorrectProductParameter(?int $product, string $error): void
    {
        $objectManager = Bootstrap::getObjectManager();
        /** @var FormKey $formKey */
        $formKey = $objectManager->get(FormKey::class);
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        /** @var SerializerInterface $serializer */
        $serializer = $objectManager->create(SerializerInterface::class);

        $request
            ->setMethod(HttpRequest::METHOD_POST)
            ->setPostValue(
                [
                    'form_key' => $formKey->getFormKey(),
                    'product' => $product,
                ]
            );

        $this->dispatch('bold_booster/digitalwallets_quote/create');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        /** @var mixed[] $responseBody */
        $responseBody = $serializer->unserialize($response->getBody());

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($responseBody['success']);
        self::assertSame($error, $responseBody['error']);
    }

    public function testReturnsErrorIfQuoteCannotBeCreated(): void
    {
        $productRepositoryStub = $this->createStub(ProductRepositoryInterface::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var ProductInterface $product */
        $product = $objectManager->create(ProductInterface::class);
        $magentoQuoteCreatorStub = $this->createStub(MagentoQuoteCreator::class);
        /** @var LocalizedException $localizedException */
        $localizedException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Could not save quote. Error: "%1"', 'Unknown error'),
            ]
        );
        /** @var FormKey $formKey */
        $formKey = $objectManager->get(FormKey::class);
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        /** @var SerializerInterface $serializer */
        $serializer = $objectManager->create(SerializerInterface::class);

        $productRepositoryStub
            ->method('getById')
            ->willReturn($product);

        $magentoQuoteCreatorStub
            ->method('createQuote')
            ->willThrowException($localizedException);

        $objectManager->configure(
            [
                ProductRepository::class => [
                    'shared' => true,
                ],
                MagentoQuoteCreator::class => [
                    'shared' => true,
                ],
            ]
        );
        // @phpstan-ignore-next-line
        $objectManager->addSharedInstance($productRepositoryStub, ProductRepository::class);
        // @phpstan-ignore-next-line
        $objectManager->addSharedInstance($magentoQuoteCreatorStub, MagentoQuoteCreator::class);

        $request
            ->setMethod(HttpRequest::METHOD_POST)
            ->setPostValue(
                [
                    'form_key' => $formKey->getFormKey(),
                    'product' => 42,
                ]
            );

        $this->dispatch('bold_booster/digitalwallets_quote/create');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        /** @var mixed[] $responseBody */
        $responseBody = $serializer->unserialize($response->getBody());

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($responseBody['success']);
        self::assertSame('Could not save quote. Error: "Unknown error"', $responseBody['error']);
    }

    public function testReturnsErrorIfCreatedQuoteCannotBeRetrieved(): void
    {
        $productRepositoryStub = $this->createStub(ProductRepositoryInterface::class);
        $objectManager = Bootstrap::getObjectManager();
        /** @var ProductInterface $product */
        $product = $objectManager->create(ProductInterface::class);
        $quoteRepositoryStub = $this->createStub(CartRepositoryInterface::class);
        /** @var LocalizedException $localizedException */
        $localizedException = $objectManager->create(
            NoSuchEntityException::class,
            [
                'phrase' => __('The requested quote does not exist.')
            ]
        );
        $magentoQuoteCreatorStub = $this->createStub(MagentoQuoteCreator::class);
        /** @var CartInterface $quote */
        $quote = $objectManager->create(
            CartInterface::class,
            [
                'data' => [
                    'entity_id' => 42
                ]
            ]
        );
        /** @var FormKey $formKey */
        $formKey = $objectManager->get(FormKey::class);
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        /** @var SerializerInterface $serializer */
        $serializer = $objectManager->create(SerializerInterface::class);

        $productRepositoryStub
            ->method('getById')
            ->willReturn($product);

        $quoteRepositoryStub
            ->method('get')
            ->willThrowException($localizedException);

        $magentoQuoteCreatorStub
            ->method('createQuote')
            ->willReturn(
                [
                    'quote' => $quote,
                    'maskedId' => '85741d5e8170b404f9f83d004c65fd0b26c1d4c8fac7f4903b94e167b693fbb60'
                ]
            );

        $objectManager->configure(
            [
                ProductRepository::class => [
                    'shared' => true,
                ],
                QuoteRepository::class => [
                    'shared' => true,
                ],
                MagentoQuoteCreator::class => [
                    'shared' => true,
                ],
            ]
        );
        // @phpstan-ignore-next-line
        $objectManager->addSharedInstance($productRepositoryStub, ProductRepository::class);
        // @phpstan-ignore-next-line
        $objectManager->addSharedInstance($quoteRepositoryStub, QuoteRepository::class);
        // @phpstan-ignore-next-line
        $objectManager->addSharedInstance($magentoQuoteCreatorStub, MagentoQuoteCreator::class);

        $request
            ->setMethod(HttpRequest::METHOD_POST)
            ->setPostValue(
                [
                    'form_key' => $formKey->getFormKey(),
                    'product' => 42,
                ]
            );

        $this->dispatch('bold_booster/digitalwallets_quote/create');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        /** @var mixed[] $responseBody */
        $responseBody = $serializer->unserialize($response->getBody());

        self::assertSame(500, $response->getStatusCode());
        self::assertFalse($responseBody['success']);
        self::assertSame('Could not retrieve newly created quote.', $responseBody['error']);
    }

    /**
     * @return array<string, array<string, string|int|null>>
     */
    public function incorrectProductParameterDataProvider(): array
    {
        return [
            'no product id' => [
                'product' => null,
                'error' => 'Please provide a product identifier to create a quote for.',
            ],
            'invalid product id' => [
                'product' => 42,
                'error' => 'Invalid product identifier "42".',
            ]
        ];
    }
}
