<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Integration;

/**
 * Place order HTTP response data model interface.
 */
interface PlaceOrderResponseInterface
{
    /**
     * Retrieve response data.
     *
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\OrderDataInterface|string[]
     */
    public function getData(): \Bold\CheckoutPaymentBooster\Api\Data\Integration\OrderDataInterface|array;

    /**
     * Retrieve response errors.
     *
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface[]
     */
    public function getErrors(): array;

    /**
     * Set response data.
     *
     * @param \Bold\CheckoutPaymentBooster\Api\Data\Integration\OrderDataInterface $data
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\PlaceOrderResponseInterface
     */
    public function setData(mixed $data): \Bold\CheckoutPaymentBooster\Api\Data\Integration\PlaceOrderResponseInterface;

    /**
     * Set response errors.
     *
     * @param \Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface[] $errors
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\PlaceOrderResponseInterface
     */
    public function setErrors(array $errors): \Bold\CheckoutPaymentBooster\Api\Data\Integration\PlaceOrderResponseInterface;

    /**
     * Add error to response.
     *
     * @param \Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface $error
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\PlaceOrderResponseInterface
     */
    public function addError(\Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface $error): \Bold\CheckoutPaymentBooster\Api\Data\Integration\PlaceOrderResponseInterface;

    /**
     * Add error with message to response.
     *
     * @param string $message
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\PlaceOrderResponseInterface
     */
    public function addErrorWithMessage(string $message): \Bold\CheckoutPaymentBooster\Api\Data\Integration\PlaceOrderResponseInterface;

    /**
     * Set response HTTP Status Code.
     *
     * @param int $code
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\PlaceOrderResponseInterface
     */
    public function setResponseHttpStatus(int $code): \Bold\CheckoutPaymentBooster\Api\Data\Integration\PlaceOrderResponseInterface;
}

