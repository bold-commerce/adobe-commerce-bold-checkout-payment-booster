<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Http\Client;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * HTTP client response data model interface.
 */
interface ResultInterface extends ExtensibleDataInterface
{
    /**
     * Retrieve response status.
     *
     * @return int
     */
    public function getStatus(): int;

    /**
     * Retrieve response errors.
     *
     * @return string[]|array{message: string, type: string, field: string, severity: string, sub_type: string}[]
     */
    public function getErrors(): array;

    /**
     * Retrieve response body.
     *
     * @return array
     */
    public function getBody(): array;

    /**
     * Retrieve response extension attributes.
     *
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Http\Client\ResultExtensionInterface|null
     */
    public function getExtensionAttributes(): ?ResultExtensionInterface;
}
