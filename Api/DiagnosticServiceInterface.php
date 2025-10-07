<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api;

use Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\DiagnosticDataInterface;

/**
 * Diagnostic Service Interface
 *
 * Provides diagnostic information about the Bold CheckoutPaymentBooster module
 * including platform info, store configuration, Bold settings, and module conflicts.
 */
interface DiagnosticServiceInterface
{
    /**
     * Retrieves diagnostic data related to the current context or system state.
     *
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Diagnostic\DiagnosticDataInterface
     *          The diagnostic data instance containing relevant information.
     * @noinspection PhpUnnecessaryFullyQualifiedNameInspection
     */
    public function getDiagnosticData(): DiagnosticDataInterface;
}
