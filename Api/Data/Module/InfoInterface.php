<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Module;

/**
 * Installed bold modules info result model interface.
 */
interface InfoInterface
{
    /**
     * Get module composer name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get module composer version.
     *
     * @return string
     */
    public function getVersion(): string;
}
