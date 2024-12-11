<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Bold\CheckoutPaymentBooster\UI\PaymentBoosterConfigProvider;

class BoldCheckoutData implements SectionSourceInterface
{
    /**
     * @var PaymentBoosterConfigProvider
     */
    private $paymentBoosterConfig;

    public function __construct(PaymentBoosterConfigProvider $paymentBoosterConfig)
    {
        $this->paymentBoosterConfig = $paymentBoosterConfig;
    }

    /**
     * @return array
     */
    public function getSectionData()
    {
        $boldConfig = $this->paymentBoosterConfig->getConfig();
        return $boldConfig['bold'] ?? [];
    }
}
