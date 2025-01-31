<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Controller\Digitalwallets\Quote;

use Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote\Deactivator;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;

use function __;

class Deactivate implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * @var Validator
     */
    private $formKeyValidator;
    /**
     * @var RequestInterface
     */
    private $request;
    /**
     * @var ResultFactory
     */
    private $resultFactory;
    /**
     * @var Deactivator
     */
    private $quoteDeactivator;

    public function __construct(
        Validator $formKeyValidator,
        RequestInterface $request,
        ResultFactory $resultFactory,
        Deactivator $quoteDeactivator
    ) {
        $this->formKeyValidator = $formKeyValidator;
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->quoteDeactivator = $quoteDeactivator;
    }

    public function execute(): ResultInterface
    {
        /** @var int|string|null $quoteId */
        $quoteId = $this->request->getParam('quote_id');

        if ($quoteId === null) {
            return $this->createErrorResult(
                (string)__('Please provide the identifier of the quote to deactivate.'),
                400
            );
        }

        try {
            $this->quoteDeactivator->deactivateQuote($quoteId);
        } catch (LocalizedException $localizedException) {
            return $this->createErrorResult($localizedException->getMessage(), 400);
        }

        return $this->createSuccessResult();
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        // @phpstan-ignore-next-line
        return !$request->isPost() || !$request->isXmlHttpRequest() || !$this->formKeyValidator->validate($request);
    }

    private function createResult(array $data, int $responseCode): ResultInterface
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        $result->setData($data);
        $result->setHttpResponseCode($responseCode);

        return $result;
    }

    private function createErrorResult(string $errorMessage, int $responseCode): ResultInterface
    {
        $data = [
            'success' => false,
            'error' => $errorMessage,
        ];

        return $this->createResult($data, $responseCode);
    }

    private function createSuccessResult(): ResultInterface
    {
        $data = [
            'success' => true,
        ];

        return $this->createResult($data, 200);
    }
}
