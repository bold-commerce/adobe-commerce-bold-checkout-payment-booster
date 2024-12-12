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
     * @return array{
     *     epsAuthToken: string,
     *     configurationGroupLabel: string,
     *     epsUrl: string,
     *     epsStaticUrl: string,
     *     gatewayId: int,
     *     jwtToken: string,
     *     url: string,
     *     shopId: string,
     *     publicOrderId: string,
     *     countries: array{
     *         is_region_visible: bool,
     *         label: string,
     *         value: string
     *     },
     *     origin: string,
     *     epsUrl: string,
     *     shopUrl: string,
     *     shopName: string,
     *     isPhoneRequired: bool,
     *     isExpressPayEnabled: bool,
     *     isCartWalletPayEnabled: bool,
     *     paymentBooster: array{
     *         payment: object{
     *             method: string
     *         }
     *     }
     * }
     */
    public function getSectionData(): array
    {
        $boldConfig = $this->paymentBoosterConfig->getConfig();
        return $boldConfig['bold'] ?? [];
    }
}
