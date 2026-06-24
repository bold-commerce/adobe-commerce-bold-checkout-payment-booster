<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Log;

use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Structured trace logger for Bold public order ID resolution and payment lifecycle.
 *
 * Writes to var/log/bold_checkout_payment_booster.log — grep for [OrderTrace].
 */
class CheckoutOrderTracer
{
    private const PREFIX = '[OrderTrace]';

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Json
     */
    private $json;

    /**
     * @param LoggerInterface $logger
     * @param Json $json
     */
    public function __construct(LoggerInterface $logger, Json $json)
    {
        $this->logger = $logger;
        $this->json = $json;
    }

    /**
     * @param string $event
     * @param array<string, mixed> $context
     * @return void
     */
    public function trace(string $event, array $context = []): void
    {
        $this->logger->info(self::PREFIX . ' ' . $event . ' ' . $this->json->serialize($context));
    }
}
