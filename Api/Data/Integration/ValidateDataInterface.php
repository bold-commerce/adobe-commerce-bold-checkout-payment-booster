<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Integration;

/**
 * HTTP response data model interface.
 */
interface ValidateDataInterface
{
    CONST VALIDATION = 'validation';

    /**
     * @return string
     */
    public function getValidation(): string;

    /**
     * @param string $validation
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\ValidateDataInterface
     */
    public function setValidation(string $validation): \Bold\CheckoutPaymentBooster\Api\Data\Integration\ValidateDataInterface;
}
