<?php

namespace Bold\CheckoutPaymentBooster\Model\RedirectToBoldCheckout;

use Bold\Checkout\Model\IsBoldCheckoutAllowedForRequest;
use Bold\Checkout\Model\RedirectToBoldCheckout\IsOrderInitializationAllowedInterface;
use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Framework\App\RequestInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Check if Bold order initialization is available for request.
 */
class IsOrderInitializationAllowedForRequest implements IsOrderInitializationAllowedInterface
{
    /**
     * @var IsBoldCheckoutAllowedForRequest
     */
    private $allowedForRequest;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param IsBoldCheckoutAllowedForRequest $allowedForRequest
     * @param Config $config
     */
    public function __construct(
        IsBoldCheckoutAllowedForRequest $allowedForRequest,
        Config $config
    ) {
        $this->allowedForRequest = $allowedForRequest;
        $this->config = $config;
    }

    /**
     * Check if Bold order initialization is available for request.
     *
     * @param CartInterface $quote
     * @param RequestInterface $request
     * @return bool
     */
    public function isAllowed(CartInterface $quote, RequestInterface $request): bool
    {
        return $this->config->isPaymentBoosterEnabled((int)$quote->getStore()->getWebsiteId())
            || $this->allowedForRequest->isAllowed($quote, $request);
    }
}
