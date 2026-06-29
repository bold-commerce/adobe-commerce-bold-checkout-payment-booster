<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Log;

use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Structured trace logger for Bold public order ID resolution and payment lifecycle.
 *
 * Writes to var/log/bold_checkout_payment_booster.log when advanced logging is enabled.
 * Grep for [OrderTracker] or a public_order_id value to follow a checkout.
 *
 * Correlation:
 * - public_order_id (and session/quote/db variants) — logged in full for Bold order tracing
 * - checkout_ref — stable hash per Magento quote_id; links events on the same cart
 * - txn_ref / bold_order_ref — hashed payment identifiers
 */
class OrderTracker
{
    private const PREFIX = '[OrderTracker]';

    private const REF_LENGTH = 12;

    /**
     * @var string[]
     */
    private const DROP_KEYS = [
        'grand_total',
        'order_total_cents',
        'url',
        'website_id',
    ];

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Json
     */
    private $json;

    /**
     * @param LoggerInterface $logger
     * @param Config $config
     * @param Json $json
     */
    public function __construct(LoggerInterface $logger, Config $config, Json $json)
    {
        $this->logger = $logger;
        $this->config = $config;
        $this->json = $json;
    }

    /**
     * @param int $websiteId
     * @param string $event
     * @param array<string, mixed> $context
     * @return void
     */
    public function trace(int $websiteId, string $event, array $context = []): void
    {
        if (!$this->config->getLogIsEnabled($websiteId)) {
            return;
        }

        $sanitized = $this->sanitizeContext($context);
        $sanitized['website_id'] = $websiteId;

        $this->logger->info(self::PREFIX . ' ' . $event . ' ' . $this->json->serialize($sanitized));
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $out = [];

        if (isset($context['quote_id']) && $context['quote_id'] !== '' && $context['quote_id'] !== null) {
            $out['checkout_ref'] = $this->reference((string)$context['quote_id'], 'quote');
        }

        foreach ($context as $key => $value) {
            if (in_array($key, self::DROP_KEYS, true)) {
                continue;
            }

            if ($key === 'quote_id') {
                $out['quote_id'] = (string)$value;
                continue;
            }

            if ($key === 'customer_id') {
                $out['customer_logged_in'] = $value !== null && $value !== '' && (int)$value > 0;
                continue;
            }

            if ($key === 'transaction_id') {
                if ($value !== null && $value !== '') {
                    $out['txn_ref'] = $this->reference((string)$value, 'txn');
                }
                continue;
            }

            if ($key === 'bold_order_id') {
                if ($value !== null && $value !== '') {
                    $out['bold_order_ref'] = $this->reference((string)$value, 'bold_order');
                }
                continue;
            }

            if ($key === 'errors') {
                $out['error_messages'] = $this->sanitizeErrors($value);
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * @param mixed $errors
     * @return string[]
     */
    private function sanitizeErrors($errors): array
    {
        if (!is_array($errors)) {
            return [];
        }

        $messages = [];
        foreach ($errors as $error) {
            if (is_array($error) && isset($error['message'])) {
                $messages[] = (string)$error['message'];
            } elseif (is_string($error)) {
                $messages[] = $error;
            }
        }

        return $messages;
    }

    private function reference(string $value, string $namespace): string
    {
        return substr(hash('sha256', $namespace . ':' . $value), 0, self::REF_LENGTH);
    }
}
