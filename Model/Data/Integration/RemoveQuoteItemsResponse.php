<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Data\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\RemoveQuoteItemsResponseInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterfaceFactory;
use Magento\Framework\Webapi\Rest\Response;

/**
 * Http client response data model.
 */
class RemoveQuoteItemsResponse implements RemoveQuoteItemsResponseInterface
{
    /**
     * @var \Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface|string[]
     */
    protected $data = [];

    /**
     * @var \Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface[]
     */
    protected $errors = [];

    /**
     * @var Response
     */
    private $response;

    /**
     * @var ErrorDataInterfaceFactory
     */
    private $errorDataFactory;

    /**
     * @param ErrorDataInterfaceFactory $errorDataFactory
     * @param Response $response
     */
    public function __construct(
        ErrorDataInterfaceFactory $errorDataFactory,
        Response $response
    ) {
        $this->errorDataFactory = $errorDataFactory;
        $this->response = $response;
    }

    /**
     * Get response data.
     *
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface|string[]
     */
    public function getData(): \Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface|array
    {
        return $this->data;
    }

    /**
     * Get errors from response body.
     *
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Set response data.
     *
     * @param \Bold\CheckoutPaymentBooster\Api\Data\Integration\QuoteDataInterface $data
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\RemoveQuoteItemsResponseInterface
     */
    public function setData(mixed $data): RemoveQuoteItemsResponseInterface
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Set response errors.
     *
     * @param \Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface[] $errors
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\RemoveQuoteItemsResponseInterface
     */
    public function setErrors(array $errors): RemoveQuoteItemsResponseInterface
    {
        $this->errors = $errors;
        return $this;
    }

    /**
     * add error to response errors.
     *
     * @param \Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface $error
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\RemoveQuoteItemsResponseInterface
     */
    public function addError(\Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface $error): RemoveQuoteItemsResponseInterface
    {
        $this->errors = array_merge([$error], $this->errors);
        return $this;
    }

    /**
     * add error by message to response errors.
     *
     * @param string $message
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\RemoveQuoteItemsResponseInterface
     */
    public function addErrorWithMessage(string $message): RemoveQuoteItemsResponseInterface
    {
        $error = $this->errorDataFactory->create()->setMessage($message);
        return $this->addError($error);
    }

    /**
     * Set response HTTPS Status Code.
     *
     * @param int $code
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\RemoveQuoteItemsResponseInterface
     */
    public function setResponseHttpStatus(int $code): RemoveQuoteItemsResponseInterface
    {
        $this->response->setHttpResponseCode($code);
        return $this;
    }
}

