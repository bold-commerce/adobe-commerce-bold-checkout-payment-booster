<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\CustomerData;

use Magento\Checkout\Model\CompositeConfigProvider;
use Magento\Checkout\Model\Session;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Bold\CheckoutPaymentBooster\UI\PaymentBoosterConfigProvider;

class BoldCheckoutData implements SectionSourceInterface
{
    /**
     * @var PaymentBoosterConfigProvider
     */
    private $paymentBoosterConfig;

    /**
     * @var CompositeConfigProvider
     */
    private $configProvider;

    /**
     * @var Session
     */
    private $checkoutSession;

    public function __construct(
        PaymentBoosterConfigProvider $paymentBoosterConfig,
        CompositeConfigProvider $configProvider,
        Session $checkoutSession
    ){
        $this->paymentBoosterConfig = $paymentBoosterConfig;
        $this->configProvider = $configProvider;
        $this->checkoutSession = $checkoutSession;
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
        $quoteId = $this->checkoutSession->getQuote()->getId();
        $sectionData = [];

        if ($quoteId !== null) {
            $sectionData['checkoutConfig'] = $this->configProvider->getConfig();
        } else {
            $sectionData['checkoutConfig'] = $this->paymentBoosterConfig->getConfigWithoutQuote();
        }

        return $sectionData;
    }
}
