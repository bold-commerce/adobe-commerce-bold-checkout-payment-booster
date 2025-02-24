<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Controller\Digitalwallets\Quote;

use Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote\Deactivator as MagentoQuoteDeactivator;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteId;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

use function is_string;

class DeactivateTest extends AbstractController
{
    /**
     * @dataProvider deactivatesQuoteSuccessfullyDataProvider
     * @param int|string $quoteId
     */
    public function testDeactivatesQuoteSuccessfully($quoteId): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $magentoQuoteDeactivatorStub = $this->createStub(MagentoQuoteDeactivator::class);
        /** @var FormKey $formKey */
        $formKey = $objectManager->get(FormKey::class);
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        /** @var SerializerInterface $serializer */
        $serializer = $objectManager->create(SerializerInterface::class);

        $objectManager->configure(
            [
                MagentoQuoteDeactivator::class => [
                    'shared' => true,
                ],
            ]
        );
        // @phpstan-ignore-next-line
        $objectManager->addSharedInstance($magentoQuoteDeactivatorStub, MagentoQuoteDeactivator::class);

        if (is_string($quoteId)) {
            $maskedQuoteIdToQuoteId = $this->createStub(MaskedQuoteIdToQuoteIdInterface::class);

            $maskedQuoteIdToQuoteId
                ->method('execute')
                ->willReturn(42);

            $objectManager->configure(
                [
                    MaskedQuoteIdToQuoteId::class => [
                        'shared' => true,
                    ],
                ]
            );
            // @phpstan-ignore-next-line
            $objectManager->addSharedInstance($maskedQuoteIdToQuoteId, MaskedQuoteIdToQuoteId::class);
        }

        $request
            ->setMethod(HttpRequest::METHOD_POST)
            ->setPostValue(
                [
                    'form_key' => $formKey->getFormKey(),
                    'quote_id' => $quoteId,
                ]
            );

        $this->dispatch('bold_booster/digitalwallets_quote/deactivate');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        /** @var array{success: bool, error?: string} $responseBody */
        $responseBody = $serializer->unserialize($response->getBody());

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($responseBody['success']);
    }

    /**
     * @dataProvider doesNotDeactivateQuoteSuccessfullyDataProvider
     * @param int|string|null $quoteId
     */
    public function testDoesNotDeactivateQuoteSuccessfully(
        $quoteId,
        string $expectedErrorMessage,
        int $expectedStatusCode
    ): void {
        $objectManager = Bootstrap::getObjectManager();
        $localizedException = $objectManager->create(
            LocalizedException::class,
            [
                'phrase' => __('Unknown error'),
            ]
        );
        $magentoQuoteDeactivatorStub = $this->createStub(MagentoQuoteDeactivator::class);
        /** @var FormKey $formKey */
        $formKey = $objectManager->get(FormKey::class);
        /** @var HttpRequest $request */
        $request = $this->getRequest();
        /** @var SerializerInterface $serializer */
        $serializer = $objectManager->create(SerializerInterface::class);

        $magentoQuoteDeactivatorStub
            ->method('deactivateQuote')
            ->willThrowException($localizedException);

        $objectManager->configure(
            [
                MagentoQuoteDeactivator::class => [
                    'shared' => true,
                ],
            ]
        );
        // @phpstan-ignore-next-line
        $objectManager->addSharedInstance($magentoQuoteDeactivatorStub, MagentoQuoteDeactivator::class);

        if (is_string($quoteId)) {
            /** @var NoSuchEntityException $noSuchEntityException */
            $noSuchEntityException = $objectManager->create(NoSuchEntityException::class);
            $maskedQuoteIdToQuoteId = $this->createStub(MaskedQuoteIdToQuoteIdInterface::class);

            $maskedQuoteIdToQuoteId
                ->method('execute')
                ->willThrowException($noSuchEntityException);

            $objectManager->configure(
                [
                    MaskedQuoteIdToQuoteId::class => [
                        'shared' => true,
                    ],
                ]
            );
            // @phpstan-ignore-next-line
            $objectManager->addSharedInstance($maskedQuoteIdToQuoteId, MaskedQuoteIdToQuoteId::class);
        }

        $request
            ->setMethod(HttpRequest::METHOD_POST)
            ->setPostValue(
                [
                    'form_key' => $formKey->getFormKey(),
                    'quote_id' => $quoteId,
                ]
            );

        $this->dispatch('bold_booster/digitalwallets_quote/deactivate');

        /** @var HttpResponse $response */
        $response = $this->getResponse();
        /** @var array{success: bool, error?: string} $responseBody */
        $responseBody = $serializer->unserialize($response->getBody());

        self::assertSame($expectedStatusCode, $response->getStatusCode());
        self::assertFalse($responseBody['success']);
        self::assertSame($expectedErrorMessage, $responseBody['error'] ?? '');
    }

    /**
     * @return array<string, array<string, int|string>>
     */
    public function deactivatesQuoteSuccessfullyDataProvider(): array
    {
        return [
            'with unmasked quote id' => [
                'quoteId' => 42,
            ],
            'with masked quote id' => [
                'quoteId' => '69cd3ead790f424ba93790175e100a97',
            ]
        ];
    }

    /**
     * @return array<string, array<string, int|string|null>>
     */
    public function doesNotDeactivateQuoteSuccessfullyDataProvider(): array
    {
        return [
            'with no quote id' => [
                'quoteId' => null,
                'expectedErrorMessage' => 'Please provide the identifier of the quote to deactivate.',
                'expectedStatusCode' => 400,
            ],
            'with invalid masked quote id' => [
                'quoteId' => 'c79249def5f24090bcc5375e56cc45b1',
                'expectedErrorMessage' =>
                    "Could not deactivate quote. Invalid quote mask identifier \"c79249def5f24090bcc5375e56cc45b1\".",
                'expectedStatusCode' => 400,
            ],
            'if exception is thrown while deactivating quote ' => [
                'quoteId' => 42,
                'expectedErrorMessage' => 'Unknown error',
                'expectedStatusCode' => 500,
            ],
        ];
    }
}
