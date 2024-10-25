<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Plugin\Quote\Model\QuoteManagement;

use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Service;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;

/**
 * Disable fastlane|express pay orders billing and shipping addresses validation plugin.
 */
class DisableBoldAddressValidationPlugin
{
    /**
     * Bold payment methods codes.
     */
    private const BOLD_METHODS_CODES = [
        Service::CODE_FASTLANE,
        Service::CODE,
    ];

    /**
     * Disable fastlane|express pay orders billing and shipping addresses validation as they may have no phone number.
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
        if (!in_array($quote->getPayment()->getMethod(), self::BOLD_METHODS_CODES)) {
            return;
        }
        $quote->getBillingAddress()->setShouldIgnoreValidation(true);
        $quote->getShippingAddress()->setShouldIgnoreValidation(true);
    }
}
