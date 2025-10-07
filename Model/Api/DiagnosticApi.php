<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Api;

use Bold\CheckoutPaymentBooster\Api\DiagnosticApiInterface;
use Bold\CheckoutPaymentBooster\Model\SimpleDiagnosticService;
use Exception;
use Magento\Framework\Exception\LocalizedException;

/**
 * Diagnostic API Service
 */
class DiagnosticApi implements DiagnosticApiInterface
{
    /**
     * @var SimpleDiagnosticService
     */
    private $diagnosticService;

    /**
     * @param SimpleDiagnosticService $diagnosticService
     */
    public function __construct(
        SimpleDiagnosticService $diagnosticService
    ) {
        $this->diagnosticService = $diagnosticService;
    }

    /**
     * @inheritDoc
     */
    public function getDiagnosticData(): array
    {
        try {
            return $this->diagnosticService->generateDiagnosticData();
        } catch (Exception $e) {
            throw new LocalizedException(__('Failed to generate diagnostic data: %1', $e->getMessage()));
        }
    }

    /**
     * @inheritDoc
     */
    public function getDiagnosticDataAsJson(): string
    {
        try {
            return $this->diagnosticService->getDiagnosticDataAsJson();
        } catch (Exception $e) {
            throw new LocalizedException(__('Failed to generate diagnostic JSON: %1', $e->getMessage()));
        }
    }

}
