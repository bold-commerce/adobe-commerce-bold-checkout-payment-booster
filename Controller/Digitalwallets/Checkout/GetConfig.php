<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Controller\Digitalwallets\Checkout;

use Bold\CheckoutPaymentBooster\ViewModel\ExpressPay;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Get Checkout config for Digital Wallets.
 */
class GetConfig implements HttpGetActionInterface
{
    /**
     * @var JsonFactory
     */
    private $jsonFactory;

    /**
     * @var ExpressPay
     */
    private $expressPayViewModel;

    /**
     * @param JsonFactory $jsonFactory
     * @param ExpressPay $expressPayViewModel
     */
    public function __construct(
        JsonFactory $jsonFactory,
        ExpressPay $expressPayViewModel
    ) {
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
        return $this->jsonFactory->create()->setData($this->expressPayViewModel->getCheckoutConfig());
    }
}
