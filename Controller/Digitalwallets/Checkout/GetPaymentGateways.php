<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Controller\Digitalwallets\Checkout;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json as JsonResult;
use Magento\Framework\Controller\Result\JsonFactory as JsonResultFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;

class GetPaymentGateways implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @var CheckoutData
     */
    private $boldCheckoutData;
    /**
     * @var JsonResultFactory
     */
    private $jsonResultFactory;
    /**
     * @var FormKeyValidator
     */
    private $formKeyValidator;

    public function __construct(
        CheckoutData $boldCheckoutData,
        JsonResultFactory $jsonResultFactory,
        FormKeyValidator $formKeyValidator
    ) {
        $this->boldCheckoutData = $boldCheckoutData;
        $this->formKeyValidator = $formKeyValidator;
        $this->jsonResultFactory = $jsonResultFactory;
    }

    public function execute(): JsonResult
    {
        /** @var JsonResult $jsonResult */
        $jsonResult = $this->jsonResultFactory->create();

        $jsonResult->setData([]);

        if ($this->boldCheckoutData->getPublicOrderId() === null) {
            return $jsonResult;
        }

        $jsonResult->setData($this->boldCheckoutData->getPaymentGateways());

        return $jsonResult;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return $request->isPost() && $request->isXmlHttpRequest() && $this->formKeyValidator->validate($request);
    }
}
