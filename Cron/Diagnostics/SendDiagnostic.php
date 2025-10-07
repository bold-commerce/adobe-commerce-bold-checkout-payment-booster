<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Cron\Diagnostics;

use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Service\Diagnostics\Send as SendService;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Cron job for sending diagnostic data
 */
class SendDiagnostic
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var SendService
     */
    private $sendService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Config $config
     * @param SendService $sendService
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        SendService $sendService,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->sendService = $sendService;
        $this->logger = $logger;
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            // Check if diagnostic sending is enabled
            if (!$this->isEnabled()) {
                $this->logger->info('Diagnostic cron job is disabled');
                return;
            }

            $this->logger->info('Starting diagnostic cron job execution');

            $response = $this->sendService->sendDiagnosticData(1);

            if (isset($response['success'])) {
                $this->logger->info('Diagnostic data sent successfully via cron', [
                    'message' => $response['message'] ?? 'No message'
                ]);
            } else {
                $this->logger->warning('Diagnostic data not sent via cron', [
                    'message' => $response['message'] ?? 'No message'
                ]);
            }
        } catch (Exception $e) {
            $this->logger->error('Error in diagnostic cron job: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Check if diagnostic sending is enabled
     *
     * @return bool
     */
    private function isEnabled(): bool
    {
        return $this->config->isDiagnosticCronEnabled(1);
    }
}
