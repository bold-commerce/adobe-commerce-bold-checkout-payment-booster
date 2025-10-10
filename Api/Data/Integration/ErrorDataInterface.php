<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Integration;

/**
 * HTTP response data model interface.
 */
interface ErrorDataInterface
{
    CONST MESSAGE = 'message';

    /**
     * @return string
     */
    public function getMessage(): string;

    /**
     * @param string $message
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface
     */
    public function setMessage(string $message): \Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface;
}
