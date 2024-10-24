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
    /**
     * Disable fastlane billing address validation as it may have no phone number.
     *
     * @param QuoteManagement $subject
     * @param Quote $quote
     * @param array $orderData
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSubmit(
        QuoteManagement $subject,
        Quote $quote,
        $orderData = []
    ) {
        if ($quote->getPayment()->getMethod() !== Service::CODE_FASTLANE) {
            return;
        }
        $quote->getBillingAddress()->setShouldIgnoreValidation(true);
    }
}
