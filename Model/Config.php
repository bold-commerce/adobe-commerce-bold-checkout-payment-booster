<?php

namespace Bold\CheckoutPaymentBooster\Model;

use Bold\Checkout\Api\ConfigManagementInterface;

/**
 * Module config.
 */
class Config
{
    private const PATH_IS_PAYMENT_BOOSTER_ENABLED = 'checkout/bold_checkout_payment_booster/is_enabled';

    /**
     * @var ConfigManagementInterface
     */
    private $configManagement;

    /**
     * @param ConfigManagementInterface $configManagement
     */
    public function __construct(
        ConfigManagementInterface $configManagement
    ) {
        $this->configManagement = $configManagement;
    }

    /**
     * Get if the Payment Booster is enabled.
     */
    public function isPaymentBoosterEnabled(int $websiteId): bool
    {
        return (bool)$this->configManagement->getValue(
            self::PATH_IS_PAYMENT_BOOSTER_ENABLED,
            $websiteId
        );
    }
}
