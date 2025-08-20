<?php

namespace Bold\CheckoutPaymentBooster\Model\Data\ExpressPay;

use Bold\CheckoutPaymentBooster\Api\Data\ExpressPay\GetInitialConfigProviderInterface;
use Bold\CheckoutPaymentBooster\UI\PaymentBoosterConfigProvider;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;

class GetInitialConfigProvider implements GetInitialConfigProviderInterface
{
    /** @var CheckoutData */
    private $checkoutData;

    /** @var PaymentBoosterConfigProvider  */
    private $paymentBoosterConfigProvider;

    /**
     * Constructor
     *
     * @param CheckoutData $checkoutData
     * @param PaymentBoosterConfigProvider $paymentBoosterConfigProvider
     */
    public function __construct(
        CheckoutData $checkoutData,
        PaymentBoosterConfigProvider $paymentBoosterConfigProvider
    ) {
        $this->checkoutData = $checkoutData;
        $this->paymentBoosterConfigProvider = $paymentBoosterConfigProvider;
    }

    /**
     * Get Initial Configuration
     *
     * @return array|mixed|mixed[]
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getInitialConfig()
    {
        $this->checkoutData->initCheckoutData();
        return $this->paymentBoosterConfigProvider->getConfig();
    }
}
