<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\UI\Payment;

use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Service;
use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Config provider for Bold Wallet Payments.
 */
class WalletPaymentsConfigProvider implements ConfigProviderInterface
{
    /**
     * @inheirtDoc
     */
    public function getConfig(): array
    {
        return [
            'bold' => [
                'walletPayments' => [
                    'payment' => [
                        'method' => Service::CODE_WALLET,
                    ],
                ],
            ],
        ];
    }
}
