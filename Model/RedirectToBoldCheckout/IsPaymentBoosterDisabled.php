<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\RedirectToBoldCheckout;

use Bold\Checkout\Model\RedirectToBoldCheckout\IsRedirectToBoldCheckoutAllowedInterface;
use Bold\CheckoutPaymentBooster\Model\Config as ModuleConfig;
use Magento\Framework\App\RequestInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Checks if Payment Booster is not enabled for website.
 */
class IsPaymentBoosterDisabled implements IsRedirectToBoldCheckoutAllowedInterface
{
    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @param ModuleConfig $moduleConfig
     */
    public function __construct(
        ModuleConfig $moduleConfig
    ) {
        $this->moduleConfig = $moduleConfig;
    }

    /**
     * @inheirtDoc
     */
    public function isAllowed(CartInterface $quote, RequestInterface $request): bool
    {
        $websiteId = (int)$quote->getStore()->getWebsiteId();

        return !$this->moduleConfig->isPaymentBoosterEnabled($websiteId);
    }
}
