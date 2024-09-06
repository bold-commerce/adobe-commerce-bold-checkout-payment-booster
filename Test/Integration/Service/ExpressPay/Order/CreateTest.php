<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Service\ExpressPay\Order;

use Bold\Checkout\Api\Data\Http\Client\ResultInterface;
use Bold\Checkout\Model\Http\BoldClient;
use Bold\CheckoutPaymentBooster\Service\ExpressPay\Order\Create;
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

class CreateTest extends TestCase
{
    /**
     * @var Quote|null
     */
    private $quote;

    /**
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     * @throws LocalizedException
     */
    public function testCreatesExpressPayOrderSuccessfully(): void
    {
        $boldApiResultMock = $this->createMock(ResultInterface::class);
        $boldClientMock = $this->createMock(BoldClient::class);
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Create $createExpressPayOrderService */
        $createExpressPayOrderService = $objectManager->create(
            Create::class,
            [
                'httpClient' => $boldClientMock
            ]
        );
        $quoteMaskId = $this->getQuoteMaskId();

        $boldApiResultMock->method('getBody')
            ->willReturn(
                [
                    'data' => [
                        'order_id' => '5d23799a-0c98-4147-914e-abd1b84aab82'
                    ]
                ]
            );

        $boldApiResultMock->method('getErrors')
            ->willReturn([]);

        $boldApiResultMock->method('getStatus')
            ->willReturn(200);

        $boldClientMock->method('post')
            ->willReturn($boldApiResultMock);

        $expectedResultData = [
            'paypal_order_id' => '5d23799a-0c98-4147-914e-abd1b84aab82'
        ];
        $actualResultData = $createExpressPayOrderService->execute(
            $quoteMaskId,
            'e4403e69-1fd2-4d8a-be28-fdbf911a20bb'
        );

        self::assertSame($expectedResultData, $actualResultData);
    }

    public function testDoesNotCreateExpressPayOrderIfQuoteMaskIdIsInvalid(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'Could not create Express Pay order. Invalid quote mask ID "bb567af9f9d44983971981a6e8eacfd6".'
        );

        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Create $createExpressPayOrderService */
        $createExpressPayOrderService = $objectManager->create(Create::class);

        $createExpressPayOrderService->execute(
            'bb567af9f9d44983971981a6e8eacfd6',
            '525f40b7-c512-4e5b-aa82-cc7276a48de9'
        );
    }

    public function testDoesNotCreateExpressPayOrderIfQuoteDoesNotExist(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Could not create Express Pay order. Invalid quote ID "42".');

        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Create $createExpressPayOrderService */
        $createExpressPayOrderService = $objectManager->create(Create::class);

        $createExpressPayOrderService->execute(42, '525f40b7-c512-4e5b-aa82-cc7276a48de9');
    }

    /**
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     */
    public function testThrowsExceptionIfApiCallThrowsException(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Could not create Express Pay order. Error: "HTTP 503 Service Unavailable"');

        $boldClientMock = $this->createMock(BoldClient::class);
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Create $createExpressPayOrderService */
        $createExpressPayOrderService = $objectManager->create(
            Create::class,
            [
                'httpClient' => $boldClientMock
            ]
        );
        $quoteMaskId = $this->getQuoteMaskId();

        $boldClientMock->method('post')
            ->willThrowException(new Exception('HTTP 503 Service Unavailable'));

        $createExpressPayOrderService->execute($quoteMaskId, 'ae066eda-f88a-4c13-938f-e8bd4e496144');
    }

    /**
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     */
    public function testThrowsExceptionIfApiCallReturnsIncorrectStatus(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('An unknown error occurred while creating the Express Pay order.');

        $boldApiResultMock = $this->createMock(ResultInterface::class);
        $boldClientMock = $this->createMock(BoldClient::class);
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Create $createExpressPayOrderService */
        $createExpressPayOrderService = $objectManager->create(
            Create::class,
            [
                'httpClient' => $boldClientMock
            ]
        );
        $quoteMaskId = $this->getQuoteMaskId();

        $boldApiResultMock->method('getErrors')
            ->willReturn([]);

        $boldApiResultMock->method('getStatus')
            ->willReturn(422);

        $boldClientMock->method('post')
            ->willReturn($boldApiResultMock);

        $createExpressPayOrderService->execute($quoteMaskId, 'ae066eda-f88a-4c13-938f-e8bd4e496144');
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
