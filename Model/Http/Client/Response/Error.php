<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Http\Client\Response;

/**
 * Http client error data model.
 */
class Error
{
    /**
     * @var int
     */
    private $code;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $message;

    /**
     * @param string $message
     * @param string $type
     * @param int $code
     */
    public function __construct(
        string $message,
        string $type = 'server.internal_error',
        int $code = 500
    ) {
        $this->message = $message;
        $this->type = $type;
        $this->code = $code;
    }

    /**
     * Get error code.
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * Get error type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get error message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }
}
