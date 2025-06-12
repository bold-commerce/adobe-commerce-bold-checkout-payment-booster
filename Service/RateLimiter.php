<?php

namespace Bold\CheckoutPaymentBooster\Service;

use Magento\Framework\App\CacheInterface;

/**
 * Simple reusable rate-limiter that uses Magento cache backend.
 */
class RateLimiter
{
    /** Default maximum number of actions per window */
    private const DEFAULT_LIMIT = 10;

    /** Default time-window in seconds */
    private const DEFAULT_WINDOW = 60;

    private CacheInterface $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Check if the provided key is within the allowed limit.
     *
     * @throws \RuntimeException when the limit is exceeded.
     */
    public function check(string $key, int $limit = self::DEFAULT_LIMIT, int $window = self::DEFAULT_WINDOW): void
    {
        // Sanitize key to avoid unexpected characters in cache identifier.
        $cacheKey = 'ai_chat_rate_' . md5($key);

        $current = (int)($this->cache->load($cacheKey) ?: 0);
        if ($current >= $limit) {
            throw new \RuntimeException(__('Rate limit exceeded. Please wait before retrying.'));
        }
        // Increment and set TTL only on first write to keep sliding window simple.
        $this->cache->save((string)($current + 1), $cacheKey, [], $window);
    }
} 