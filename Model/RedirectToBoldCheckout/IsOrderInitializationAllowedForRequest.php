<?php
declare(strict_types=1);

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
        $websiteId = (int)$quote->getStore()->getWebsiteId();

        return $this->config->isPaymentBoosterEnabled($websiteId)
            || $this->allowedForRequest->isAllowed($quote, $request);
    }
}
