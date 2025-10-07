<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api;

/**
 * Diagnostic API Interface
 */
interface DiagnosticApiInterface
{
    /**
     * Get diagnostic configuration data as array
     *
     * @return array<string, mixed>
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getDiagnosticData(): array;

    /**
     * Get diagnostic configuration data as JSON string
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getDiagnosticDataAsJson(): string;
}
