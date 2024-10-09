<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Service\ExpressPay\Order;

use Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterface;
use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Bold\CheckoutPaymentBooster\Service\ExpressPay\Order\Get as GetExpressPayOrder;
use Bold\CheckoutPaymentBooster\Service\ExpressPay\Order\Update;
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

use function reset;

class UpdateTest extends TestCase
{
    /**
     * @var Quote|null
     */
    private $quote;

    /**
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     * @throws LocalizedException
     */
    public function testUpdatesExpressPayOrderSuccessfully(): void
    {
        $this->expectNotToPerformAssertions();

        $getExpressPayOrderMock = $this->createMock(GetExpressPayOrder::class);
        $boldApiResultMock = $this->createMock(ResultInterface::class);
        $boldClientMock = $this->createMock(BoldClient::class);
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Update $updateExpressPayOrderService */
        $updateExpressPayOrderService = $objectManager->create(
            Update::class,
            [
                'getExpressPayOrder' => $getExpressPayOrderMock,
                'httpClient' => $boldClientMock,
            ]
        );
        $quoteMaskId = $this->getQuoteMaskId();

        $getExpressPayOrderMock->method('execute')
            ->willReturn(
                [
                    'shipping_address' => [
                        'country' => 'US',
                        'city' => 'CityM',
                    ],
                ]
            );

        $boldApiResultMock->method('getErrors')
            ->willReturn([]);

        $boldApiResultMock->method('getStatus')
            ->willReturn(204);

        $boldClientMock->method('patch')
            ->willReturn($boldApiResultMock);

        $updateExpressPayOrderService->execute(
            $quoteMaskId,
            'e08fac5cffd6467389ce3aac1df1eeeb',
            '472df0908785478d8509fbfa8ef532eb'
        );
    }

    public function testDoesNotUpdateExpressPayOrderIfQuoteMaskIdIsInvalid(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Could not update Express Pay order. Invalid quote mask ID "d3b46018dbff492d8ad339229f9a30f7".'
        );

        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Update $updateExpressPayOrderService */
        $updateExpressPayOrderService = $objectManager->create(Update::class);

        $updateExpressPayOrderService->execute(
            'd3b46018dbff492d8ad339229f9a30f7',
            '97fb04bc9669476bb271985ffa1875d9',
            'ff369b06761c46dba3e3acb4e08347fd'
        );
    }

    public function testDoesNotUpdateExpressPayOrderIfQuoteDoesNotExist(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Could not update Express Pay order. Invalid quote ID "42".');

        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Update $updateExpressPayOrderService */
        $updateExpressPayOrderService = $objectManager->create(Update::class);

        $updateExpressPayOrderService->execute(
            42,
            'b76f88547476441a88c81fa0905d4505',
            '34695237c2914d3e953d7d5d81503b4d'
        );
    }

    /**
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     */
    public function testThrowsExceptionIfApiCallThrowsException(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Could not update Express Pay order. Error: "HTTP 503 Service Unavailable"');

        $getExpressPayOrderMock = $this->createMock(GetExpressPayOrder::class);
        $boldClientMock = $this->createMock(BoldClient::class);
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Update $updateExpressPayOrderService */
        $updateExpressPayOrderService = $objectManager->create(
            Update::class,
            [
                'getExpressPayOrder' => $getExpressPayOrderMock,
                'httpClient' => $boldClientMock
            ]
        );
        $quoteMaskId = $this->getQuoteMaskId();

        $getExpressPayOrderMock->method('execute')
            ->willReturn(
                [
                    'shipping_address' => [
                        'country' => 'US',
                        'city' => 'CityM',
                    ]
                ]
            );

        $boldClientMock->method('patch')
            ->willThrowException(new Exception('HTTP 503 Service Unavailable'));

        $updateExpressPayOrderService->execute(
            $quoteMaskId,
            'c8ced8a1f3584d378bd35fd039aeec98',
            'b1db99325b9a47738a87f355ff409a75'
        );
    }

    /**
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     */
    public function testThrowsExceptionIfApiCallReturnsIncorrectStatus(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('An unknown error occurred while updating the Express Pay order.');

        $getExpressPayOrderMock = $this->createMock(GetExpressPayOrder::class);
        $boldApiResultMock = $this->createMock(ResultInterface::class);
        $boldClientMock = $this->createMock(BoldClient::class);
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Update $updateExpressPayOrderService */
        $updateExpressPayOrderService = $objectManager->create(
            Update::class,
            [
                'getExpressPayOrder' => $getExpressPayOrderMock,
                'httpClient' => $boldClientMock
            ]
        );
        $quoteMaskId = $this->getQuoteMaskId();

        $getExpressPayOrderMock->method('execute')
            ->willReturn(
                [
                    'shipping_address' => [
                        'country' => 'US',
                        'city' => 'CityM',
                    ]
                ]
            );

        $boldApiResultMock->method('getErrors')
            ->willReturn([]);

        $boldApiResultMock->method('getStatus')
            ->willReturn(422);

        $boldClientMock->method('patch')
            ->willReturn($boldApiResultMock);

        $updateExpressPayOrderService->execute(
            $quoteMaskId,
            '6622461eb1174b57b688277efc3ffb5b',
            '2e03ed6f555f4b0196c7d2d4d72e0f7c'
        );
    }

    /**
     * @dataProvider apiErrorsDataProvider
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     * @param array<string, array<string, array<int, array<string, string>|string>|string>> $apiErrors
     */
    public function testThrowsExceptionIfApiCallReturnsErrors(string $expectedExceptionMessage, array $apiErrors): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $getExpressPayOrderMock = $this->createMock(GetExpressPayOrder::class);
        $boldApiResultMock = $this->createMock(ResultInterface::class);
        $boldClientMock = $this->createMock(BoldClient::class);
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Update $updateExpressPayOrderService */
        $updateExpressPayOrderService = $objectManager->create(
            Update::class,
            [
                'getExpressPayOrder' => $getExpressPayOrderMock,
                'httpClient' => $boldClientMock
            ]
        );
        $quoteMaskId = $this->getQuoteMaskId();

        $getExpressPayOrderMock->method('execute')
            ->willReturn(
                [
                    'shipping_address' => [
                        'country' => 'US',
                        'city' => 'CityM',
                    ]
                ]
            );

        $boldApiResultMock->method('getErrors')
            ->willReturn($apiErrors);

        $boldClientMock->method('patch')
            ->willReturn($boldApiResultMock);

        $updateExpressPayOrderService->execute(
            $quoteMaskId,
            'cd389ccd-08a0-4651-aa33-cb7db6327b95',
            'f36c10ca-b8e2-4187-9e15-7a3d9a6f99b4'
        );
    }

    /**
     * @return array<string, array<string, array<int, array<string, string>|string>|string>>
     */
    public function apiErrorsDataProvider(): array
    {
        return [
            'full API error payload' => [
                'expectedExceptionMessage' => 'Could not update Express Pay order. Errors: "The order data.selected '
                    . 'shipping option.id field is required with order data.selected shipping option., The order '
                    . 'data.selected shipping option.label field must be a string., The order data.selected shipping '
                    . 'option.label field is required with order data.selected shipping option."',
                'apiErrors' => [
                    [
                        'message' => 'The order data.selected shipping option.id field is required with order '
                            . 'data.selected shipping option.',
                        'type' => 'order',
                        'field' => 'order_data.selected_shipping_option.id',
                        'severity' => 'validation',
                        'sub_type' => 'wallet_pay'
                    ],
                    [
                        'message' => 'The order data.selected shipping option.label field must be a string.',
                        'type' => 'order',
                        'field' => 'order_data.selected_shipping_option.label',
                        'severity' => 'validation',
                        'sub_type' => 'wallet_pay'
                    ],
                    [
                        'message' => 'The order data.selected shipping option.label field is required with order '
                            . 'data.selected shipping option.',
                        'type' => 'order',
                        'field' => 'order_data.selected_shipping_option.label',
                        'severity' => 'validation',
                        'sub_type' => 'wallet_pay'
                    ]
                ]
            ],
            'basic API error payload' => [
                'expectedExceptionMessage' => 'Could not update Express Pay order. Error: "The access token is invalid '
                    . 'or has expired"',
                'apiErrors' => [
                    'The access token is invalid or has expired'
                ]
            ]
        ];
    }

    private function getQuote(): Quote
    {
        if ($this->quote !== null) {
            return $this->quote;
        }

        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
        $searchCriteria = $searchCriteriaBuilder->addFilter('reserved_order_id', 'test_order_1')
            ->create();
        /** @var CartRepositoryInterface $cartRepository */
        $cartRepository = $objectManager->create(CartRepositoryInterface::class);
        /** @var Quote[] $quotes */
        $quotes = $cartRepository->getList($searchCriteria)
            ->getItems();
        /** @var Quote $quote */
        $quote = reset($quotes) ?: $objectManager->create(CartInterface::class);

        return $this->quote = $quote;
    }

    private function getQuoteMaskId(): string
    {
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId */
        $quoteIdToMaskedQuoteId = $objectManager->create(QuoteIdToMaskedQuoteIdInterface::class);
        /** @var int|string|null $quoteId */
        $quoteId = $this->getQuote()->getId();

        try {
            $quoteMaskId = $quoteIdToMaskedQuoteId->execute((int)$quoteId);
        } catch (NoSuchEntityException $e) {
            $quoteMaskId = '';
        }

        return $quoteMaskId;
    }
}
