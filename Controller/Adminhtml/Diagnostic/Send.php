<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Controller\Adminhtml\Diagnostic;

use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Service\Diagnostics\Send as SendService;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Send Diagnostic Data Controller
 */
class Send extends Action
{
    /** @var Config  */
    private $config;

    /** @var JsonFactory  */
    private $jsonFactory;

    /** @var SendService  */
    private $sendService;

    /**
     * @param Context $context
     * @param Config $config
     * @param JsonFactory $jsonFactory
     * @param SendService $sendService
     */
    public function __construct(
        Context $context,
        Config $config,
        JsonFactory $jsonFactory,
        SendService $sendService
    ) {
        $this->config = $config;
        $this->jsonFactory = $jsonFactory;
        $this->sendService = $sendService;
        parent::__construct($context);
    }

    /**
     * Executes the task of sending diagnostic data to the Bold API.
     *
     * This method delegates to the Send service to handle the actual
     * diagnostic data sending logic.
     *
     * @return Json
     * @noinspection PhpMissingReturnTypeInspection
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $result = $this->jsonFactory->create();

        // Use the Send service to handle the diagnostic sending
        $websiteId = $this->getRequest()->getParam('website_id') ?? $this->config->getCurrentWebsiteId();
        $response = $this->sendService->sendDiagnosticData($websiteId);

        return $result->setData($response);
    }
}
