<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Config;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Magento\Quote\Model\Quote;

/**
 * Is Bold Checkout payment is applicable for current quote.
 */
class CanUseCheckoutValueHandler implements ValueHandlerInterface
{
    /**
     * @var CheckoutData
     */
    private $checkoutData;

    /**
     * @param CheckoutData $checkoutData
     */
    public function __construct(CheckoutData $checkoutData)
    {
        $this->checkoutData = $checkoutData;
    }

    /**
     * @inheritDoc
     * @phpstan-param mixed[] $subject
     */
    public function handle(array $subject, $storeId = null): bool
    {
        /** @var Quote $quote */
        $quote = $this->checkoutData->getQuote();

        return $this->checkoutData->getPublicOrderId() !== null
            && !$quote->getIsMultiShipping();
    }
}
