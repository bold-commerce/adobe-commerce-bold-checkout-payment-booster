<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Data\Integration;

use Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryCheckResponseInterface;
use Magento\Framework\Webapi\Rest\Response;

/**
 * Inventory check response data model.
 */
class InventoryCheckResponse implements InventoryCheckResponseInterface
{
    /**
     * @var \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryCheckDataInterface|array<empty, empty>
     */
    private $data = [];

    /**
     * @var \Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface[]
     */
    private $errors = [];

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
     * @inheritDoc
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryCheckDataInterface|array<empty, empty>
     */
    public function getData(): \Bold\CheckoutPaymentBooster\Api\Data\Integration\InventoryCheckDataInterface|array
    {
        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function setData(mixed $data): InventoryCheckResponseInterface
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @inheritDoc
     */
    public function setErrors(array $errors): InventoryCheckResponseInterface
    {
        $this->errors = $errors;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addError(\Bold\CheckoutPaymentBooster\Api\Data\Integration\ErrorDataInterface $error): InventoryCheckResponseInterface
    {
        $this->errors = array_merge([$error], $this->errors);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addErrorWithMessage(string $message): InventoryCheckResponseInterface
    {
        $error = $this->errorDataFactory->create()->setMessage($message);
        return $this->addError($error);
    }

    /**
     * @inheritDoc
     */
    public function setResponseHttpStatus(int $code): InventoryCheckResponseInterface
    {
        $this->response->setHttpResponseCode($code);
        return $this;
    }
}
