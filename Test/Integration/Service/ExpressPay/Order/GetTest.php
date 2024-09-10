<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Service\ExpressPay\Order;

use Bold\Checkout\Api\Data\Http\Client\ResultInterface;
use Bold\Checkout\Model\Http\BoldClient;
use Bold\CheckoutPaymentBooster\Service\ExpressPay\Order\Get;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class GetTest extends TestCase
{
    /**
     * @throws LocalizedException
     */
    public function testGetsExpressPayOrderSuccessfully(): void
    {
        $expressPayOrderData = [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'jsmithn@example.com',
            'shipping_address' => [
                'address_line_1' => '123 Example Street',
                'address_line_2' => 'Unit 42',
                'city' => 'Some City',
                'country' => 'US',
                'province' => 'SS',
                'postal_code' => '01234',
                'phone' => '123-456-7890'
            ],
            'billing_address' => [
                'address_line_1' => '123 Example Street',
                'address_line_2' => 'Unit 42',
                'city' => 'Some City',
                'country' => 'US',
                'province' => 'SS',
                'postal_code' => '01234',
                'phone' => '123-456-7890'
            ]
        ];
        $boldApiResultMock = $this->createMock(ResultInterface::class);
        $boldClientMock = $this->createMock(BoldClient::class);
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Get $getExpressPayOrderService */
        $getExpressPayOrderService = $objectManager->create(
            Get::class,
            [
                'httpClient' => $boldClientMock
            ]
        );

        $boldApiResultMock->method('getBody')
            ->willReturn($expressPayOrderData);

        $boldApiResultMock->method('getErrors')
            ->willReturn([]);

        $boldApiResultMock->method('getStatus')
            ->willReturn(200);

        $boldClientMock->method('get')
            ->willReturn($boldApiResultMock);

        $actualExpressPayOrderData = $getExpressPayOrderService->execute(
            'c7215964-94b5-4a64-8e5d-152ad284cedf',
            '87ef83fe-b43c-4c40-9c96-1413b9d11b79'
        );

        self::assertSame($expressPayOrderData, $actualExpressPayOrderData);
    }

    public function testThrowsExceptionIfApiCallThrowsException(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Could not get Express Pay order. Error: "HTTP 503 Service Unavailable"');

        $boldClientMock = $this->createMock(BoldClient::class);
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Get $getExpressPayOrderService */
        $getExpressPayOrderService = $objectManager->create(
            Get::class,
            [
                'httpClient' => $boldClientMock
            ]
        );

        $boldClientMock->method('get')
            ->willThrowException(new Exception('HTTP 503 Service Unavailable'));

        $getExpressPayOrderService->execute(
            '2765b685-4623-4089-9bb1-b85d6853149b',
            'e072601d-8723-4746-b43b-a7a17b833cfd'
        );
    }

    /**
     * @magentoDataFixture Bold_CheckoutPaymentBooster::Test/Integration/_files/quote_with_shipping_tax_and_discount.php
     */
    public function testThrowsExceptionIfApiCallReturnsIncorrectStatus(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('An unknown error occurred while getting the Express Pay order.');

        $boldApiResultMock = $this->createMock(ResultInterface::class);
        $boldClientMock = $this->createMock(BoldClient::class);
        /** @var ObjectManagerInterface $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        /** @var Get $getExpressPayOrderService */
        $getExpressPayOrderService = $objectManager->create(
            Get::class,
            [
                'httpClient' => $boldClientMock
            ]
        );

        $boldApiResultMock->method('getErrors')
            ->willReturn([]);

        $boldApiResultMock->method('getStatus')
            ->willReturn(422);

        $boldClientMock->method('get')
            ->willReturn($boldApiResultMock);

        $getExpressPayOrderService->execute(
            '64082511-1cfd-4772-b0c1-250871f738d4',
            '582e95b1-1a45-4b85-8d0c-b71850e986cd'
        );
    }
}
