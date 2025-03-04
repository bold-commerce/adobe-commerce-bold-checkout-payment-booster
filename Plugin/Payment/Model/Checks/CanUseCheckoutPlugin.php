<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Plugin\Payment\Model\Checks;

use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Service as PaymentGatewayService;
use Closure;
use Magento\Payment\Model\Checks\CanUseCheckout;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Model\Quote;

class CanUseCheckoutPlugin
{
    public function aroundIsApplicable(
        CanUseCheckout $subject,
        Closure $proceed,
        MethodInterface $paymentMethod,
        Quote $quote
    ): bool {
        /* Work-around for the Checkout Session intermittently not having the Bold Public Order ID, causing the check in
           `\Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Config\CanUseCheckoutValueHandler::handle()` to fail. */

        if (
            $paymentMethod->getCode() !== PaymentGatewayService::CODE
            || $quote->getExtensionAttributes()->getBoldOrderId() === null
        ) {
            return $proceed($paymentMethod, $quote);
        }

        return true;
    }
}
