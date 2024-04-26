<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\RedirectToBoldCheckout;

use Bold\Checkout\Model\RedirectToBoldCheckout\IsRedirectToBoldCheckoutAllowedInterface;
use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Framework\App\RequestInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Checks if Payment Booster is not enabled for website.
 */
class IsPaymentBoosterDisabled implements IsRedirectToBoldCheckoutAllowedInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @param Config $config
     */
    public function __construct(
        Config $config
    ) {
        $this->config = $config;
    }

    /**
     * Checks if Payment Booster is not enabled for website.
     *
     * @param CartInterface $quote
     * @param RequestInterface $request
     * @return bool
     */
    public function isAllowed(CartInterface $quote, RequestInterface $request): bool
    {
        $websiteId = (int)$quote->getStore()->getWebsiteId();

        return !$this->config->isPaymentBoosterEnabled($websiteId);
    }
}
