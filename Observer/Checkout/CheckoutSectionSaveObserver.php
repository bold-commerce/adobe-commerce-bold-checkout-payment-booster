<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Checkout;

use Bold\CheckoutPaymentBooster\Model\BoldIntegration;
use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\ShopId;
use Exception;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Save shop id when checkout configuration is saved and update an integration.
 * Should not be separated as integration creation depends on shop id.
 */
class CheckoutSectionSaveObserver implements ObserverInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var ShopId
     */
    private $shopId;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var BoldIntegration
     */
    private $updateIntegration;

    /**
     * @param Config $config
     * @param ShopId $shopId
     * @param StoreManagerInterface $storeManager
     * @param BoldIntegration $updateIntegration
     */
    public function __construct(
        Config                $config,
        ShopId                $shopId,
        StoreManagerInterface $storeManager,
        BoldIntegration       $updateIntegration
    ) {
        $this->config = $config;
        $this->shopId = $shopId;
        $this->storeManager = $storeManager;
        $this->updateIntegration = $updateIntegration;
    }

    /**
     * Perform post-save actions.
     *
     * @param Observer $observer
     * @return void
     * @throws Exception
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $websiteId = (int)$event->getWebsite() ?: (int)$this->storeManager->getWebsite(true)->getId();
        $this->updateShopId($websiteId);
        $this->updateIntegration($event, $websiteId);
    }

    /**
     * Retrieve shop id from Bold and save it in config.
     *
     * @param int $websiteId
     * @return void
     * @throws Exception
     */
    public function updateShopId(int $websiteId): void
    {
        $this->config->setShopId($websiteId, null);
        $this->config->setShopId($websiteId, $this->shopId->getShopId($websiteId));
    }

    /**
     * Update integration on configuration change or if it is absent.
     *
     * @param Event $event
     * @param int $websiteId
     * @return void
     */
    private function updateIntegration(Event $event, int $websiteId): void
    {
        $changedPaths = $event->getChangedPaths();
        if (!array_intersect($changedPaths, Config::INTEGRATION_PATHS)
            && $this->updateIntegration->getStatus($websiteId) !== null) {
            return;
        }
        $this->updateIntegration->update($websiteId);
    }
}
