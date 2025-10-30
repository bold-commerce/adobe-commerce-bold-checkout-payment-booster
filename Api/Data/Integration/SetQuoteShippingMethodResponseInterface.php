<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data\Integration;

/**
 * HTTP response data model interface for set quote shipping method.
 */
interface SetQuoteShippingMethodResponseInterface
{
    /**
     * Retrieve response data.
     *
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface|string[]
     */
    public function getData(): \Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface|array;

    /**
     * Retrieve response errors.
     *
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface[]
     */
    public function getErrors(): array;

    /**
     * set response data.
     *
     * @param \Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface $data
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\SetQuoteShippingMethodResponseInterface
     */
    public function setData(mixed $data): \Bold\CheckoutPaymentBooster\Api\Data\Integration\SetQuoteShippingMethodResponseInterface;

    /**
     * set response errors.
     *
     * @param \Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface[] $errors
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\SetQuoteShippingMethodResponseInterface
     */
    public function setErrors(array $errors): \Bold\CheckoutPaymentBooster\Api\Data\Integration\SetQuoteShippingMethodResponseInterface;

    /**
     * set response errors.
     *
     * @param \Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface $error
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\SetQuoteShippingMethodResponseInterface
     */
    public function addError(\Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface $error): \Bold\CheckoutPaymentBooster\Api\Data\Integration\SetQuoteShippingMethodResponseInterface;

    /**
     * set response errors.
     *
     * @param string $message
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\SetQuoteShippingMethodResponseInterface
     */
    public function addErrorWithMessage(string $message): \Bold\CheckoutPaymentBooster\Api\Data\Integration\SetQuoteShippingMethodResponseInterface;

    /**
     * set response HTTP Status Code.
     *
     * @param int $code
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\SetQuoteShippingMethodResponseInterface
     */
    public function setResponseHttpStatus(int $code): \Bold\CheckoutPaymentBooster\Api\Data\Integration\SetQuoteShippingMethodResponseInterface;
}

