<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Service\ExpressPay\Order;

use Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultInterface;
use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;
use Bold\CheckoutPaymentBooster\Service\ExpressPay\Order\Create;
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\Quote\Model\ResourceModel\Quote\Item as QuoteItemResource;
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
     * @magentoDataFixture Magento/ConfigurableProduct/_files/tax_rule.php
     * @magentoDataFixture Magento/SalesRule/_files/cart_rule_with_coupon_5_off_no_condition.php
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
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

        $quote = $this->getQuote();
        self::assertNotNull($quote->getId(), 'Fixture quote ID should exist.');
        self::assertGreaterThan(0, (int)$quote->getItemsCount(), 'Fixture quote should have items_count > 0.');
        self::assertNotEmpty($quote->getAllVisibleItems(), 'Fixture quote should have visible items.');

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
            'order_id' => '5d23799a-0c98-4147-914e-abd1b84aab82'
        ];
        $actualResultData = $createExpressPayOrderService->execute(
            $quoteMaskId,
            'ff152513-f548-11ef-b987-3a475e3f6277',
            'e4403e69-1fd2-4d8a-be28-fdbf911a20bb',
            'dynamic',
            false,
            ''
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
            'ff152513-f548-11ef-b987-3a475e3f6277',
            '525f40b7-c512-4e5b-aa82-cc7276a48de9',
            'dynamic',
            false,
            ''
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

        $createExpressPayOrderService->execute(
            42,
            'ff152513-f548-11ef-b987-3a475e3f6277',
            '525f40b7-c512-4e5b-aa82-cc7276a48de9',
            'dynamic',
            false,
            ''
        );
    }

    /**
     * @magentoDataFixture Magento/ConfigurableProduct/_files/tax_rule.php
     * @magentoDataFixture Magento/SalesRule/_files/cart_rule_with_coupon_5_off_no_condition.php
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
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

        $createExpressPayOrderService->execute(
            $quoteMaskId,
            'ff152513-f548-11ef-b987-3a475e3f6277',
            'ae066eda-f88a-4c13-938f-e8bd4e496144',
            'dynamic',
            false,
            ''
        );
    }

    /**
     * @magentoDataFixture Magento/ConfigurableProduct/_files/tax_rule.php
     * @magentoDataFixture Magento/SalesRule/_files/cart_rule_with_coupon_5_off_no_condition.php
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
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

        $createExpressPayOrderService->execute(
            $quoteMaskId,
            'ff152513-f548-11ef-b987-3a475e3f6277',
            'ae066eda-f88a-4c13-938f-e8bd4e496144',
            'dynamic',
            false,
            ''
        );
    }

    /**
     * @dataProvider apiErrorsDataProvider
     * @magentoDataFixture Magento/ConfigurableProduct/_files/tax_rule.php
     * @magentoDataFixture Magento/SalesRule/_files/cart_rule_with_coupon_5_off_no_condition.php
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     * @param array<string, array<string, array<int, array<string, string>|string>|string>> $apiErrors
     */
    public function testThrowsExceptionIfApiCallReturnsErrors(string $expectedExceptionMessage, array $apiErrors): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

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
            ->willReturn($apiErrors);

        $boldClientMock->method('post')
            ->willReturn($boldApiResultMock);

        $createExpressPayOrderService->execute(
            $quoteMaskId,
            'ff152513-f548-11ef-b987-3a475e3f6277',
            '182011ba-9d43-47b7-9b74-8c234531ce20',
            'dynamic',
            false,
            ''
        );
    }

    /**
     * @return array<string, array<string, array<int, array<string, string>|string>|string>>
     */
    public function apiErrorsDataProvider(): array
    {
        return [
            'full API error payload' => [
                'expectedExceptionMessage' => 'Could not create Express Pay order. Errors: "The order data.selected '
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
                'expectedExceptionMessage' => 'Could not create Express Pay order. Error: "The access token is invalid '
                    . 'or has expired"',
                'apiErrors' => [
                    'The access token is invalid or has expired'
                ]
            ]
        ];
    }

    // ─── pre-validation guards (quote active / cart not empty) ────────────────

    /**
     * When the quote is no longer active (e.g. it was deactivated after a previous order),
     * Create::execute() must throw a LocalizedException before calling the Bold API.
     *
     * This prevents wallet_pay requests for stale quotes that would fail on Bold's side.
     *
     * @magentoDataFixture Magento/ConfigurableProduct/_files/tax_rule.php
     * @magentoDataFixture Magento/SalesRule/_files/cart_rule_with_coupon_5_off_no_condition.php
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     */
    public function testThrowsWhenQuoteIsInactive(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        // Deactivate the quote directly
        $quote = $this->getQuote();
        $quote->setIsActive(false);
        $objectManager->create(CartRepositoryInterface::class)->save($quote);

        // Clear repository cache so Create::execute() loads the quote fresh from DB
        $quoteRepository = $objectManager->get(QuoteRepository::class);
        if (method_exists($quoteRepository, '_resetState')) {
            $quoteRepository->_resetState();
        }

        /** @var Create $service */
        $service = $objectManager->create(Create::class);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/no longer active|cart is empty/i');

        $service->execute(
            $this->getQuoteMaskId(),
            'ff152513-f548-11ef-b987-3a475e3f6277',
            'e4403e69-1fd2-4d8a-be28-fdbf911a20bb',
            'dynamic',
            false,
            ''
        );
    }

    /**
     * When the cart is empty (all items removed), Create::execute() must throw
     * a LocalizedException before calling the Bold API.
     *
     * @magentoDataFixture Magento/ConfigurableProduct/_files/tax_rule.php
     * @magentoDataFixture Magento/SalesRule/_files/cart_rule_with_coupon_5_off_no_condition.php
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     */
    public function testThrowsWhenCartIsEmpty(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        // Remove all items from the quote and persist to DB (removeItem() only marks deleted;
        // SaveHandler does not delete rows, so we delete items via resource and save quote)
        $quote = $this->getQuote();
        $quoteId = (int) $quote->getId();
        /** @var QuoteItemResource $quoteItemResource */
        $quoteItemResource = $objectManager->create(QuoteItemResource::class);
        foreach ($quote->getAllItems() as $item) {
            $quoteItemResource->delete($item);
        }
        $quote->setItems([]);
        $quote->setItemsCount(0);
        $quote->setItemsQty(0);
        $objectManager->create(CartRepositoryInterface::class)->save($quote);

        // Clear repository cache so Create::execute() loads the quote fresh from DB
        $quoteRepository = $objectManager->get(QuoteRepository::class);
        if (method_exists($quoteRepository, '_resetState')) {
            $quoteRepository->_resetState();
        }

        $this->quote = null;

        /** @var Create $service */
        $service = $objectManager->create(Create::class);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/no longer active|cart is empty/i');

        $service->execute(
            $this->getQuoteMaskId(),
            'ff152513-f548-11ef-b987-3a475e3f6277',
            'e4403e69-1fd2-4d8a-be28-fdbf911a20bb',
            'dynamic',
            false,
            ''
        );
    }

    private function getQuote(): \Magento\Quote\Api\Data\CartInterface
    {
        if ($this->quote !== null) {
            return $this->quote;
        }

        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $objectManager->create(SearchCriteriaBuilder::class);
        $searchCriteria = $searchCriteriaBuilder->addFilter('reserved_order_id', 'test_order_with_shipping_tax_discount')
            ->create();
        /** @var CartRepositoryInterface $cartRepository */
        $cartRepository = $objectManager->create(CartRepositoryInterface::class);
        /** @var Quote[] $quotes */
        $quotes = $cartRepository->getList($searchCriteria)
            ->getItems();
        /** @var Quote|false $quote */
        $quote = reset($quotes);

        if (!$quote instanceof Quote) {
            self::fail('Fixture quote with reserved_order_id "test_order_with_shipping_tax_discount" was not found.');
        }

        $quote = $cartRepository->get((int)$quote->getId());

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
