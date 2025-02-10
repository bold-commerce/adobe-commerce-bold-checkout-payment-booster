<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api;

use Bold\CheckoutPaymentBooster\Api\Data\Module\ResultInterface;

/**
 * Get all installed Bold modules versions.
 */
interface GetModulesInfoInterface
{
    /**
     * Get all installed Bold modules information.
     *
     * @param string $shopId
     * @return \Bold\CheckoutPaymentBooster\Api\Data\Module\ResultInterface
     * @throws \Magento\Framework\Exception\LocalizedException in case of wrong shop ID.
     */
    public function getModulesInfo(string $shopId): ResultInterface;
}
