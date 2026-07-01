<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Model\Log;

use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\Log\OrderTracker;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OrderTrackerTest extends TestCase
{
    private const PUBLIC_ORDER_ID = 'HoytrcPr1RDlbnlUxbgiXL4V489726qjj1g9Sm6P2xi2eSR4zUnWunfR9ageBBD7';

    /**
     * @var Config&MockObject
     */
    private $config;

    /**
     * @var LoggerInterface&MockObject
     */
    private $logger;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testTraceKeepsPublicOrderIdButSanitizesPii(): void
    {
        $this->config->method('getLogIsEnabled')->willReturn(true);

        $logged = '';
        $this->logger->expects(self::once())
            ->method('info')
            ->willReturnCallback(static function (string $message) use (&$logged): void {
                $logged = $message;
            });

        $tracker = new OrderTracker($this->logger, $this->config, new Json());
        $tracker->trace(1, 'hydrate_start', [
            'quote_id' => '815438',
            'public_order_id' => self::PUBLIC_ORDER_ID,
            'customer_id' => 6683,
            'grand_total' => 144.97,
            'url' => 'checkout/orders/shop/' . self::PUBLIC_ORDER_ID . '/payments/auth/full',
        ]);

        self::assertStringContainsString(self::PUBLIC_ORDER_ID, $logged);
        self::assertStringContainsString('"public_order_id":', $logged);
        self::assertStringNotContainsString('6683', $logged);
        self::assertStringNotContainsString('144.97', $logged);
        self::assertStringContainsString('"checkout_ref":', $logged);
        self::assertStringContainsString('"customer_logged_in":true', $logged);
    }

    public function testSamePublicOrderIdGrepableAcrossEvents(): void
    {
        $this->config->method('getLogIsEnabled')->willReturn(true);

        $messages = [];
        $this->logger->method('info')
            ->willReturnCallback(static function (string $message) use (&$messages): void {
                $messages[] = $message;
            });

        $tracker = new OrderTracker($this->logger, $this->config, new Json());
        $tracker->trace(1, 'init_checkout_data_resumed', [
            'quote_id' => '42',
            'public_order_id' => self::PUBLIC_ORDER_ID,
        ]);
        $tracker->trace(1, 'auth_full_success', [
            'quote_id' => '42',
            'public_order_id' => self::PUBLIC_ORDER_ID,
            'transaction_id' => '72D00553JH658592J',
        ]);

        self::assertCount(2, $messages);
        self::assertStringContainsString(self::PUBLIC_ORDER_ID, $messages[0]);
        self::assertStringContainsString(self::PUBLIC_ORDER_ID, $messages[1]);
        self::assertStringContainsString('"txn_ref":', $messages[1]);
        self::assertStringNotContainsString('72D00553JH658592J', $messages[1]);
    }

    public function testResolveEventKeepsDistinctPublicOrderIdSources(): void
    {
        $this->config->method('getLogIsEnabled')->willReturn(true);

        $logged = '';
        $this->logger->expects(self::once())
            ->method('info')
            ->willReturnCallback(static function (string $message) use (&$logged): void {
                $logged = $message;
            });

        $sessionId = self::PUBLIC_ORDER_ID;
        $extensionId = '7WYlBAN947oJKsRlDsU9c9beLiFtY6kRffiZ8iNinSYF9ptoIgpPIy1Iq4PzrHIL';

        $tracker = new OrderTracker($this->logger, $this->config, new Json());
        $tracker->trace(1, 'resolve_public_order_id', [
            'quote_id' => '815438',
            'session_public_order_id' => $sessionId,
            'quote_ext_public_order_id' => $extensionId,
            'db_public_order_id' => $sessionId,
            'resolved_public_order_id' => $sessionId,
            'public_order_ids_match' => false,
        ]);

        self::assertStringContainsString($sessionId, $logged);
        self::assertStringContainsString($extensionId, $logged);
        self::assertStringContainsString('"session_public_order_id":', $logged);
        self::assertStringContainsString('"quote_ext_public_order_id":', $logged);
        self::assertStringContainsString('"public_order_ids_match":false', $logged);
    }

    public function testTraceSkippedWhenLoggingDisabled(): void
    {
        $this->config->method('getLogIsEnabled')->willReturn(false);
        $this->logger->expects(self::never())->method('info');

        $tracker = new OrderTracker($this->logger, $this->config, new Json());
        $tracker->trace(1, 'hydrate_start', ['public_order_id' => self::PUBLIC_ORDER_ID]);
    }
}
