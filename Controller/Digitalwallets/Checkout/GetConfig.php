<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Controller\Digitalwallets\Checkout;

use Bold\CheckoutPaymentBooster\ViewModel\ExpressPay;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Get Checkout config for Digital Wallets.
 */
class GetConfig implements HttpPostActionInterface
{
    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var JsonFactory
     */
    private $jsonFactory;
    /**
     * @var ExpressPay
     */
    private $expressPayViewModel;

    /**
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     * @param ExpressPay $expressPayViewModel
     */
    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        ExpressPay $expressPayViewModel
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->expressPayViewModel = $expressPayViewModel;
    }

    /**
     * Get Checkout non-cache config for Digital Wallets on product|cart|mini-cart pages.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $requestParameters = $this->request->getParams();

        if (empty($requestParameters['pageSource'])) {
            return $this->jsonFactory->create()->setData([]);
        }

        return $this->jsonFactory->create()->setData(
            $this->expressPayViewModel->getCheckoutConfig($requestParameters['pageSource'])
        );
    }
}
