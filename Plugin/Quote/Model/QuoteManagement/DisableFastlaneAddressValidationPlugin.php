<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Plugin\Quote\Model\QuoteManagement;

use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Service;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;

/**
 * Disable fastlane billing address validation plugin.
 */
class DisableFastlaneAddressValidationPlugin
{
    private const METHODS = [
        Service::CODE_FASTLANE,
        Service::CODE,
    ];

    /**
     * Disable fastlane billing address validation as it may have no phone number.
     *
     * @param QuoteManagement $subject
     * @param Quote $quote
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSubmit(
        QuoteManagement $subject,
        Quote $quote
    ) {
        if (in_array($quote->getPayment()->getMethod(), self::METHODS, true)) {
            $quote->getBillingAddress()->setShouldIgnoreValidation(true);
            $quote->getShippingAddress()->setShouldIgnoreValidation(true);
        }
    }
}
