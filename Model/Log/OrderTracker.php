<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Log;

use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Structured trace logger for Bold public order ID resolution and payment lifecycle.
 *
 * Writes to var/log/bold_checkout_payment_booster.log when advanced logging is enabled — grep for [OrderTracker].
 */
class OrderTracker
{
    private const PREFIX = '[OrderTracker]';

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

        $this->logger->info(self::PREFIX . ' ' . $event . ' ' . $this->json->serialize($context));
    }
}
