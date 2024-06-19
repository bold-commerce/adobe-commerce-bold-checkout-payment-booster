<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Bold\Checkout\Api\ConfigManagementInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Module config.
 */
class Config
{
    private const PATH_IS_PAYMENT_BOOSTER_ENABLED = 'checkout/bold_checkout_payment_booster/is_payment_booster_enabled';
    private const PATH_IS_FASTLANE_ENABLED = 'checkout/bold_checkout_payment_booster/is_fastlane_enabled';
    public const PATH_PAYMENT_CSS = 'checkout/bold_checkout_custom_elements/payment_css';
    public const PATH_FASTLANE_PAYMENT_TITLE = 'checkout/bold_checkout_payment_booster/fastlane_payment_title';

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
     * Check if the Payment Booster is enabled.
     *
     * @param int $websiteId
     * @return bool
     * @throws LocalizedException
     */
    public function isPaymentBoosterEnabled(int $websiteId): bool
    {
        return (bool)$this->configManagement->getValue(
            self::PATH_IS_PAYMENT_BOOSTER_ENABLED,
            $websiteId
        );
    }

    /**
     * Check if the Fastlane is enabled.
     *
     * @param int $websiteId
     * @return bool
     * @throws LocalizedException
     */
    public function isFastlaneEnabled(int $websiteId): bool
    {
        return (bool)$this->configManagement->getValue(
            self::PATH_IS_FASTLANE_ENABLED,
            $websiteId
        );
    }

    /**
     * Get payment iframe additional css.
     *
     * @param int $websiteId
     * @return string
     */
    public function getPaymentCss(int $websiteId): string
    {
        return (string)$this->configManagement->getValue(
            self::PATH_PAYMENT_CSS,
            $websiteId
        );
    }
}
