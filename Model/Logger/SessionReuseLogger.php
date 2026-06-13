<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Logger;

use Psr\Log\LoggerInterface;

/**
 * Wallet session-reuse diagnostics (CHK-9605).
 *
 * Writes to var/log/bold_checkout_payment_booster.log — same file as HTTP request logs.
 * Grep: grep '\[Bold Session\]' var/log/bold_checkout_payment_booster.log
 */
class SessionReuseLogger
{
    public const LOG_PREFIX = '[Bold Session]';

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param array<string, bool|int|string|null> $context
     */
    public function info(string $event, array $context = []): void
    {
        // Use debug level so entries land in the same handler as RequestsLogger output.
        $this->logger->debug($this->formatMessage($event, $context));
    }

    /**
     * @param array<string, bool|int|string|null> $context
     */
    public function warning(string $event, array $context = []): void
    {
        $this->logger->warning($this->formatMessage('WARNING: ' . $event, $context));
    }

    /**
     * @param array<string, bool|int|string|null> $context
     */
    private function formatMessage(string $event, array $context): string
    {
        if ($context === []) {
            return self::LOG_PREFIX . ' ' . $event;
        }

        $pairs = [];
        foreach ($context as $key => $value) {
            if ($value === null) {
                $pairs[] = $key . '=null';
                continue;
            }

            if (is_bool($value)) {
                $pairs[] = $key . '=' . ($value ? 'true' : 'false');
                continue;
            }

            $pairs[] = $key . '=' . (string)$value;
        }

        return self::LOG_PREFIX . ' ' . $event . ' | ' . implode(' ', $pairs);
    }
}
