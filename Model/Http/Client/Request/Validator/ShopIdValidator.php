<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Http\Client\Request\Validator;

use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Validate shop id against given store id.
 */
class ShopIdValidator
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(Config $config, StoreManagerInterface $storeManager)
    {
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    /**
     * Verify if shop id belongs to given store.
     *
     * @param string $shopId
     * @param int $storeId
     * @return void
     * @throws LocalizedException
     */
    public function validate(string $shopId, int $storeId): void
    {
        $websiteId = (int)$this->storeManager->getStore($storeId)->getWebsiteId();
        $shopIdentifier = $this->config->getShopId($websiteId);
        if ($shopIdentifier !== $shopId) {
            throw new LocalizedException(__('Shop Id "%1" is incorrect.', $shopId));
        }
    }
}
