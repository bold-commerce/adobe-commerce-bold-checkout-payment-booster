<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Config;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Payment\Gateway\Config\ValueHandlerInterface;
use Magento\Payment\Model\Checks\CanUseForCountry\CountryProvider;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;

/**
 *  Is Bold Checkout payment is applicable for current quote.
 */
class CanUseCheckoutValueHandler implements ValueHandlerInterface
{
    /**
     * @var CountryProvider
     */
    private $countryProvider;

    /**
     * @var CheckoutData
     */
    private $checkoutData;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var string
     */
    private $paymentMethodGroup;

    /**
     * @param CheckoutData $checkoutData
     * @param ScopeConfigInterface $scopeConfig
     * @param CountryProvider $countryProvider
     * @param string $paymentMethodGroup
     */
    public function __construct(
        CheckoutData         $checkoutData,
        ScopeConfigInterface $scopeConfig,
        CountryProvider $countryProvider,
        string               $paymentMethodGroup
    ) {
        $this->checkoutData = $checkoutData;
        $this->scopeConfig = $scopeConfig;
        $this->paymentMethodGroup = $paymentMethodGroup;
        $this->countryProvider = $countryProvider;
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
            && !$quote->getIsMultiShipping() && $this->isAllowedCountry($quote);
    }

    /**
     * Check if country is allowed
     *
     * @param Quote $quote
     * @return bool
     */
    private function isAllowedCountry(Quote $quote)
    {
        $countryId = $this->countryProvider->getCountry($quote);
        $storeId = $quote->getStoreId();

        $configPathPrefix = 'payment/' . $this->paymentMethodGroup . '/';

        if (
            !$this->scopeConfig->isSetFlag(
                $configPathPrefix . 'allowspecific',
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
        ) {
            return true; // All countries are allowed - no restriction
        }

        $allowedCountries = explode(',', (string) $this->scopeConfig->getValue(
            $configPathPrefix . 'specificcountry',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        return in_array($countryId, $allowedCountries, true);
    }
}
