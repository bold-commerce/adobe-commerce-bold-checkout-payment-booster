<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Plugin\GraphQl;

use Bold\CheckoutPaymentBooster\Plugin\GraphQl\AddToCartPlugin;
use Bold\CheckoutPaymentBooster\Service\RateLimiter;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Integration test for AddToCartPlugin
 */
class AddToCartPluginTest extends TestCase
{
    private AddToCartPlugin $plugin;
    private RateLimiter $rateLimiter;
    private LoggerInterface $logger;
    private RemoteAddress $remoteAddress;
    private ObjectManagerInterface $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->rateLimiter = $this->objectManager->get(RateLimiter::class);
        $this->logger = $this->objectManager->get('Bold\CheckoutPaymentBooster\Logger\AiChat');
        $this->remoteAddress = $this->objectManager->get(RemoteAddress::class);
        
        $this->plugin = new AddToCartPlugin(
            $this->logger,
            $this->rateLimiter,
            $this->remoteAddress
        );
    }

    /**
     * Test plugin is properly instantiated
     */
    public function testPluginIsProperlyInstantiated(): void
    {
        $this->assertInstanceOf(AddToCartPlugin::class, $this->plugin);
    }

    /**
     * Test successful GraphQL resolver call within rate limit
     */
    public function testSuccessfulResolverCallWithinRateLimit(): void
    {
        $subject = new \stdClass();
        $proceed = function(...$args) {
            return [
                'cart' => [
                    'items' => [
                        [
                            'id' => 1,
                            'product' => ['sku' => 'test-product'],
                            'quantity' => 1
                        ]
                    ]
                ]
            ];
        };

        // Mock GraphQL resolver arguments
        $mockArgs = [
            null, // field
            null, // value
            null, // context
            null, // info
            [
                'input' => [
                    'cart_id' => 'test_cart_123',
                    'cart_items' => [
                        [
                            'sku' => 'test-product',
                            'quantity' => 1
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->plugin->aroundResolve($subject, $proceed, ...$mockArgs);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('cart', $result);
        $this->assertArrayHasKey('items', $result['cart']);
        $this->assertCount(1, $result['cart']['items']);
    }

    /**
     * Test rate limiting blocks requests when limit exceeded
     */
    public function testRateLimitingBlocksRequestsWhenLimitExceeded(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rate limit exceeded. Please wait before retrying.');

        $subject = new \stdClass();
        $proceed = function(...$args) {
            return ['cart' => ['items' => []]];
        };

        $mockArgs = [
            null, null, null, null,
            ['input' => ['cart_id' => 'test', 'cart_items' => []]]
        ];

        // Create a unique test key and exhaust the rate limit
        $testKey = 'plugin_test_' . uniqid();
        
        // Mock the RemoteAddress to return our test key
        $mockRemoteAddress = $this->createMock(RemoteAddress::class);
        $mockRemoteAddress->method('getRemoteAddress')->willReturn($testKey);
        
        $pluginWithMockAddress = new AddToCartPlugin(
            $this->logger,
            $this->rateLimiter,
            $mockRemoteAddress
        );

        // Exhaust the rate limit (default is 10)
        for ($i = 0; $i < 10; $i++) {
            $this->rateLimiter->check($testKey);
        }

        // This should throw the rate limit exception
        $pluginWithMockAddress->aroundResolve($subject, $proceed, ...$mockArgs);
    }

    /**
     * Test plugin logs requests properly
     */
    public function testPluginLogsRequestsProperly(): void
    {
        // Create a mock logger to capture log calls
        $mockLogger = $this->createMock(LoggerInterface::class);
        
        // Expect info logs for request and response
        $mockLogger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive(
                [$this->stringContains('[AI Chat] addSimpleProductsToCart called')],
                [$this->stringContains('[AI Chat] addSimpleProductsToCart result')]
            );

        $pluginWithMockLogger = new AddToCartPlugin(
            $mockLogger,
            $this->rateLimiter,
            $this->remoteAddress
        );

        $subject = new \stdClass();
        $proceed = function(...$args) {
            return ['cart' => ['items' => []]];
        };

        $mockArgs = [
            null, null, null, null,
            ['input' => ['cart_id' => 'test', 'cart_items' => []]]
        ];

        $pluginWithMockLogger->aroundResolve($subject, $proceed, ...$mockArgs);
    }

    /**
     * Test plugin logs rate limit warnings
     */
    public function testPluginLogsRateLimitWarnings(): void
    {
        // Create a mock logger to capture warning logs
        $mockLogger = $this->createMock(LoggerInterface::class);
        $mockLogger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('[AI Chat] Rate limit exceeded'));

        // Create mock remote address that returns a test key
        $testKey = 'warning_test_' . uniqid();
        $mockRemoteAddress = $this->createMock(RemoteAddress::class);
        $mockRemoteAddress->method('getRemoteAddress')->willReturn($testKey);

        $pluginWithMocks = new AddToCartPlugin(
            $mockLogger,
            $this->rateLimiter,
            $mockRemoteAddress
        );

        $subject = new \stdClass();
        $proceed = function(...$args) {
            return ['cart' => ['items' => []]];
        };

        $mockArgs = [
            null, null, null, null,
            ['input' => ['cart_id' => 'test', 'cart_items' => []]]
        ];

        // Exhaust the rate limit
        for ($i = 0; $i < 10; $i++) {
            $this->rateLimiter->check($testKey);
        }

        // This should log a warning and throw an exception
        $this->expectException(RuntimeException::class);
        $pluginWithMocks->aroundResolve($subject, $proceed, ...$mockArgs);
    }

    /**
     * Test plugin handles unknown IP address gracefully
     */
    public function testPluginHandlesUnknownIpAddressGracefully(): void
    {
        // Mock remote address that returns null
        $mockRemoteAddress = $this->createMock(RemoteAddress::class);
        $mockRemoteAddress->method('getRemoteAddress')->willReturn(null);

        $pluginWithMockAddress = new AddToCartPlugin(
            $this->logger,
            $this->rateLimiter,
            $mockRemoteAddress
        );

        $subject = new \stdClass();
        $proceed = function(...$args) {
            return ['cart' => ['items' => []]];
        };

        $mockArgs = [
            null, null, null, null,
            ['input' => ['cart_id' => 'test', 'cart_items' => []]]
        ];

        // Should not throw exception and should use 'unknown' as key
        $result = $pluginWithMockAddress->aroundResolve($subject, $proceed, ...$mockArgs);
        $this->assertIsArray($result);
    }

    /**
     * Test plugin preserves original resolver arguments
     */
    public function testPluginPreservesOriginalResolverArguments(): void
    {
        $subject = new \stdClass();
        $originalArgs = [
            'field' => 'testField',
            'value' => ['test' => 'value'],
            'context' => ['user' => 'test'],
            'info' => ['operation' => 'add'],
            [
                'input' => [
                    'cart_id' => 'preserve_test',
                    'cart_items' => [
                        ['sku' => 'preserve-product', 'quantity' => 2]
                    ]
                ]
            ]
        ];

        $capturedArgs = null;
        $proceed = function(...$args) use (&$capturedArgs) {
            $capturedArgs = $args;
            return ['cart' => ['items' => []]];
        };

        $this->plugin->aroundResolve($subject, $proceed, ...$originalArgs);

        $this->assertEquals($originalArgs, $capturedArgs);
    }

    /**
     * Test plugin works with different cart item configurations
     * 
     * @dataProvider cartItemsDataProvider
     */
    public function testPluginWorksWithDifferentCartItemConfigurations(array $cartItems): void
    {
        $subject = new \stdClass();
        $proceed = function(...$args) use ($cartItems) {
            return ['cart' => ['items' => $cartItems]];
        };

        $mockArgs = [
            null, null, null, null,
            ['input' => ['cart_id' => 'test', 'cart_items' => $cartItems]]
        ];

        $result = $this->plugin->aroundResolve($subject, $proceed, ...$mockArgs);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('cart', $result);
        $this->assertEquals($cartItems, $result['cart']['items']);
    }

    /**
     * Data provider for cart items test
     * 
     * @return array
     */
    public function cartItemsDataProvider(): array
    {
        return [
            'single item' => [
                [['sku' => 'single-product', 'quantity' => 1]]
            ],
            'multiple items' => [
                [
                    ['sku' => 'product-1', 'quantity' => 1],
                    ['sku' => 'product-2', 'quantity' => 2]
                ]
            ],
            'empty cart' => [[]],
            'item with options' => [
                [
                    [
                        'sku' => 'configurable-product',
                        'quantity' => 1,
                        'selected_options' => ['color' => 'red', 'size' => 'L']
                    ]
                ]
            ]
        ];
    }
} 