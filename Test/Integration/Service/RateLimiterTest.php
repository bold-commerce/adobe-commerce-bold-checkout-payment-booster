<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\Service;

use Bold\CheckoutPaymentBooster\Service\RateLimiter;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Integration test for RateLimiter service
 */
class RateLimiterTest extends TestCase
{
    private RateLimiter $rateLimiter;
    private CacheInterface $cache;
    private ObjectManagerInterface $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->cache = $this->objectManager->get(CacheInterface::class);
        $this->rateLimiter = $this->objectManager->get(RateLimiter::class);
    }

    /**
     * Test that rate limiter allows requests within limit
     */
    public function testAllowsRequestsWithinLimit(): void
    {
        $testKey = 'test_key_' . uniqid();
        $limit = 3;
        $window = 60;

        // Test requests within limit
        for ($i = 1; $i <= $limit; $i++) {
            $this->rateLimiter->check($testKey, $limit, $window);
        }

        // If we reach here, no exception was thrown
        $this->assertTrue(true);
    }

    /**
     * Test that rate limiter blocks requests exceeding limit
     */
    public function testBlocksRequestsExceedingLimit(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Rate limit exceeded. Please wait before retrying.');

        $testKey = 'test_key_' . uniqid();
        $limit = 2;
        $window = 60;

        // Fill up the limit
        for ($i = 1; $i <= $limit; $i++) {
            $this->rateLimiter->check($testKey, $limit, $window);
        }

        // This should throw an exception
        $this->rateLimiter->check($testKey, $limit, $window);
    }

    /**
     * Test rate limiter with different keys
     */
    public function testDifferentKeysHaveSeparateLimits(): void
    {
        $testKey1 = 'test_key_1_' . uniqid();
        $testKey2 = 'test_key_2_' . uniqid();
        $limit = 2;
        $window = 60;

        // Use up limit for first key
        for ($i = 1; $i <= $limit; $i++) {
            $this->rateLimiter->check($testKey1, $limit, $window);
        }

        // Second key should still work
        $this->rateLimiter->check($testKey2, $limit, $window);
        $this->rateLimiter->check($testKey2, $limit, $window);

        // First key should be blocked
        $this->expectException(RuntimeException::class);
        $this->rateLimiter->check($testKey1, $limit, $window);
    }

    /**
     * Test default limit and window values
     */
    public function testDefaultLimitAndWindow(): void
    {
        $testKey = 'test_default_' . uniqid();
        
        // Test with default values (10 requests per 60 seconds)
        for ($i = 1; $i <= 10; $i++) {
            $this->rateLimiter->check($testKey);
        }

        // 11th request should be blocked
        $this->expectException(RuntimeException::class);
        $this->rateLimiter->check($testKey);
    }

    /**
     * Test custom limit and window
     */
    public function testCustomLimitAndWindow(): void
    {
        $testKey = 'test_custom_' . uniqid();
        $customLimit = 5;
        $customWindow = 30;

        // Test with custom values
        for ($i = 1; $i <= $customLimit; $i++) {
            $this->rateLimiter->check($testKey, $customLimit, $customWindow);
        }

        // Next request should be blocked
        $this->expectException(RuntimeException::class);
        $this->rateLimiter->check($testKey, $customLimit, $customWindow);
    }

    /**
     * Test that cache key is properly generated
     */
    public function testCacheKeyGeneration(): void
    {
        $testKey = 'test_cache_key';
        $expectedCacheKey = 'ai_chat_rate_' . md5($testKey);
        
        // Make a request to generate cache entry
        $this->rateLimiter->check($testKey, 5, 60);
        
        // Check that cache entry exists
        $cacheValue = $this->cache->load($expectedCacheKey);
        $this->assertEquals('1', $cacheValue);
        
        // Make another request
        $this->rateLimiter->check($testKey, 5, 60);
        
        // Check that counter increased
        $cacheValue = $this->cache->load($expectedCacheKey);
        $this->assertEquals('2', $cacheValue);
    }

    /**
     * Clean up cache entries after each test
     */
    protected function tearDown(): void
    {
        // Clean up cache entries that might have been created during tests
        $this->cache->clean(['ai_chat_rate']);
        parent::tearDown();
    }
} 