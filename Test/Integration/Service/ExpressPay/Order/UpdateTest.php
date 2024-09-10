<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Service\ExpressPay\Order;

use Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterface;
use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
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

        $boldApiResultMock = $this->createMock(ResultInterface::class);
        $boldClientMock = $this->createMock(BoldClient::class);
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Update $updateExpressPayOrderService */
        $updateExpressPayOrderService = $objectManager->create(
            Update::class,
            [
                'httpClient' => $boldClientMock
            ]
        );
        $quoteMaskId = $this->getQuoteMaskId();

        $boldApiResultMock->method('getErrors')
            ->willReturn([]);

        $boldApiResultMock->method('getStatus')
            ->willReturn(204);

        $boldClientMock->method('patch')
            ->willReturn($boldApiResultMock);

        $updateExpressPayOrderService->execute($quoteMaskId, 'e08fac5cffd6467389ce3aac1df1eeeb');
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

        $updateExpressPayOrderService->execute('d3b46018dbff492d8ad339229f9a30f7', '97fb04bc9669476bb271985ffa1875d9');
    }

    public function testDoesNotUpdateExpressPayOrderIfQuoteDoesNotExist(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Could not update Express Pay order. Invalid quote ID "42".');

        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Update $updateExpressPayOrderService */
        $updateExpressPayOrderService = $objectManager->create(Update::class);

        $updateExpressPayOrderService->execute(42, 'b76f88547476441a88c81fa0905d4505');
    }

    /**
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     */
    public function testThrowsExceptionIfApiCallThrowsException(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Could not update Express Pay order. Error: "HTTP 503 Service Unavailable"');

        $boldClientMock = $this->createMock(BoldClient::class);
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Update $updateExpressPayOrderService */
        $updateExpressPayOrderService = $objectManager->create(
            Update::class,
            [
                'httpClient' => $boldClientMock
            ]
        );
        $quoteMaskId = $this->getQuoteMaskId();

        $boldClientMock->method('patch')
            ->willThrowException(new Exception('HTTP 503 Service Unavailable'));

        $updateExpressPayOrderService->execute($quoteMaskId, 'c8ced8a1f3584d378bd35fd039aeec98');
    }

    /**
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     */
    public function testThrowsExceptionIfApiCallReturnsIncorrectStatus(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('An unknown error occurred while updating the Express Pay order.');

        $boldApiResultMock = $this->createMock(ResultInterface::class);
        $boldClientMock = $this->createMock(BoldClient::class);
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Update $updateExpressPayOrderService */
        $updateExpressPayOrderService = $objectManager->create(
            Update::class,
            [
                'httpClient' => $boldClientMock
            ]
        );
        $quoteMaskId = $this->getQuoteMaskId();

        $boldApiResultMock->method('getErrors')
            ->willReturn([]);

        $boldApiResultMock->method('getStatus')
            ->willReturn(422);

        $boldClientMock->method('patch')
            ->willReturn($boldApiResultMock);

        $updateExpressPayOrderService->execute($quoteMaskId, '6622461eb1174b57b688277efc3ffb5b');
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
