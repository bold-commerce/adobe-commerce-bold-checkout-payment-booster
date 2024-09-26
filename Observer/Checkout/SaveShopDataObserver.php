<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Checkout;

use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\RemoteStateAuthority\GenerateSharedSecret;
use Bold\CheckoutPaymentBooster\Model\RemoteStateAuthority\RegisterSharedSecret;
use Bold\CheckoutPaymentBooster\Model\ShopId;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Bold\CheckoutPaymentBooster\Model\Http\BoldClient;

/**
 * Save shop Id and register shared secret when checkout configuration is saved.
 */
class SaveShopDataObserver implements ObserverInterface
{
    private const FLOW_CREATE_URL = 'checkout/shop/{shop_identifier}/flows';
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
     * @var GenerateSharedSecret
     */
    private $generateSharedSecret;

    /**
     * @var RegisterSharedSecret
     */
    private $registerSharedSecret;

    /**
     * @var BoldClient
     */
    private $boldClient;
    /**
     * @param Config $config
     * @param ShopId $shopId
     * @param StoreManagerInterface $storeManager
     * @param GenerateSharedSecret $generateSharedSecret
     * @param RegisterSharedSecret $registerSharedSecret
     * @param BoldClient $boldClient
     */
    public function __construct(
        Config                $config,
        ShopId                $shopId,
        StoreManagerInterface $storeManager,
        GenerateSharedSecret  $generateSharedSecret,
        RegisterSharedSecret  $registerSharedSecret,
        BoldClient            $boldClient
    ) {
        $this->config = $config;
        $this->shopId = $shopId;
        $this->storeManager = $storeManager;
        $this->generateSharedSecret = $generateSharedSecret;
        $this->registerSharedSecret = $registerSharedSecret;
        $this->boldClient = $boldClient;
    }

    /**
     * Sync shop id and shared secret.
     *
     * @param Observer $observer
     * @return void
     * @throws Exception
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $websiteId = (int)$event->getWebsite() ?: (int)$this->storeManager->getWebsite(true)->getId();
        $this->saveShopId($websiteId);
        $this->saveSharedSecret($websiteId);
        $this->getOrCreatePaymentBoosterFlowID($websiteId);
    }

    /**
     * Retrieve shop id from Bold and save it in config.
     *
     * @param int $websiteId
     * @return void
     * @throws Exception
     */
    private function saveShopId(int $websiteId): void {
        $this->config->setShopId($websiteId, null);
        $this->config->setShopId($websiteId, $this->shopId->getShopId($websiteId));
    }

    /**
     * Load or generate new shared secret and register it.
     *
     * @param int $websiteId
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function saveSharedSecret(int $websiteId): void {
        $sharedSecret = $this->config->getSharedSecret($websiteId);
        if (!$sharedSecret) {
            $sharedSecret = $this->generateSharedSecret->execute();
            $this->config->setSharedSecret($websiteId, $sharedSecret);
        }
        $this->registerSharedSecret->execute($websiteId, $sharedSecret);
    }

    private function getOrCreatePaymentBoosterFlowID(int $websiteId): void
    {
        $defaultFlowId = $this->config->getPaymentBoosterFlowID($websiteId);
        if (!$defaultFlowId) {
            $body = [
                'flow_name' => 'Bold Booster for Paypal',
                'flow_id' => 'bold-booster-m2',
                'flow_type' => 'custom'
            ];
            $result = $this->boldClient->post($websiteId, self::FLOW_CREATE_URL, $body);
            if ($result->getErrors()) {
                $message = isset(current($result->getErrors())['message'])
                    ? __(current($result->getErrors())['message'])
                    : __('Something went wrong while setting up Payment Booster. Please Try Again. If the error persists please contact Bold Support.');
                throw new LocalizedException($message);
            }
            $this->config->setPaymentBoosterFlowID($websiteId, $result->getBody()['data']['flows'][0]['flow_id']);
        }
    }
}
