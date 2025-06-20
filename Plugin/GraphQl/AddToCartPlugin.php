<?php

namespace Bold\CheckoutPaymentBooster\Plugin\GraphQl;

use Bold\CheckoutPaymentBooster\Service\RateLimiter;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Psr\Log\LoggerInterface;

/**
 * Plugin to log GraphQL add-to-cart operations and apply rate limiting.
 */
class AddToCartPlugin
{
    private LoggerInterface $logger;
    private RateLimiter $rateLimiter;
    private RemoteAddress $remoteAddress;

    public function __construct(
        LoggerInterface $logger,
        RateLimiter $rateLimiter,
        RemoteAddress $remoteAddress
    ) {
        $this->logger = $logger;
        $this->rateLimiter = $rateLimiter;
        $this->remoteAddress = $remoteAddress;
    }

    /**
     * Around plugin to log and rate-limit addSimpleProductsToCart resolver.
     *
     * @param object $subject
     * @param callable $proceed
     * @param mixed ...$args
     * @return mixed
     */
    public function aroundResolve($subject, callable $proceed, ...$args)
    {
        // Derive user key (IP address is simplest for guests)
        $userKey = $this->remoteAddress->getRemoteAddress() ?? 'unknown';

        // Rate limit check
        try {
            $this->rateLimiter->check($userKey);
        } catch (\RuntimeException $e) {
            // Log and rethrow to GraphQL layer
            $this->logger->warning('[AI Chat] Rate limit exceeded', ['ip' => $userKey]);
            throw $e;
        }

        // Log incoming args (only cart_items to avoid verbose data)
        $this->logger->info('[AI Chat] addSimpleProductsToCart called', [
            'ip' => $userKey,
            'args' => $args[4] ?? []
        ]);

        $result = $proceed(...$args);

        // Log result item ids if available
        $this->logger->info('[AI Chat] addSimpleProductsToCart result', [
            'ip' => $userKey,
            'result' => $result
        ]);

        return $result;
    }
} 