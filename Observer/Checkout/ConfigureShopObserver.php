<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Checkout;

use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\Eps\AddDomainToCorsAllowList;
use Bold\CheckoutPaymentBooster\Model\PaymentBooster\FlowService;
use Bold\CheckoutPaymentBooster\Model\RemoteStateAuthority\GenerateSharedSecret;
use Bold\CheckoutPaymentBooster\Model\RemoteStateAuthority\RegisterSharedSecret;
use Bold\CheckoutPaymentBooster\Model\ShopId;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Save shop Id and register shared secret when checkout configuration is saved.
 */
class ConfigureShopObserver implements ObserverInterface
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
     * @var GenerateSharedSecret
     */
    private $generateSharedSecret;

    /**
     * @var RegisterSharedSecret
     */
    private $registerSharedSecret;

    /**
     * @var FlowService
     */
    private $flowService;

    /**
     * @var AddDomainToCorsAllowList
     */
    private $addDomainToCorsAllowList;

    /**
     * @param Config $config
     * @param ShopId $shopId
     * @param StoreManagerInterface $storeManager
     * @param GenerateSharedSecret $generateSharedSecret
     * @param RegisterSharedSecret $registerSharedSecret
     * @param FlowService $flowService
     */
    public function __construct(
        Config $config,
        ShopId $shopId,
        StoreManagerInterface $storeManager,
        GenerateSharedSecret $generateSharedSecret,
        RegisterSharedSecret $registerSharedSecret,
        FlowService $flowService,
        AddDomainToCorsAllowList $addDomainToCorsAllowList
    ) {
        $this->config = $config;
        $this->shopId = $shopId;
        $this->storeManager = $storeManager;
        $this->generateSharedSecret = $generateSharedSecret;
        $this->registerSharedSecret = $registerSharedSecret;
        $this->flowService = $flowService;
        $this->addDomainToCorsAllowList = $addDomainToCorsAllowList;
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
        $this->addDomainToCorsAllowList($websiteId);
        $this->getOrCreatePaymentBoosterFlowID($websiteId);
    }

    /**
     * Retrieve shop id from Bold and save it in config.
     *
     * @param int $websiteId
     * @return void
     * @throws Exception
     */
    private function saveShopId(int $websiteId): void
    {
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
    private function saveSharedSecret(int $websiteId): void
    {
        $sharedSecret = $this->config->getSharedSecret($websiteId);
        if (!$sharedSecret) {
            $sharedSecret = $this->generateSharedSecret->execute();
            $this->config->setSharedSecret($websiteId, $sharedSecret);
        }
        $this->registerSharedSecret->execute($websiteId, $sharedSecret);
    }

    /**
     * Get or create Payment Booster Flow ID.
     *
     * @param int $websiteId
     * @return void
     * @throws LocalizedException
     */
    private function getOrCreatePaymentBoosterFlowID(int $websiteId): void
    {
        $defaultFlowId = $this->config->getBoldBoosterFlowID($websiteId);
        if (!$defaultFlowId) {
            try {
                $this->flowService->createAndSetBoldBoosterFlowID($websiteId);
            } catch (LocalizedException $e) {
                throw $e;
            } catch (Exception $e) {
                throw new LocalizedException(
                    __(
                        'Something went wrong while setting up Payment Booster. Please Try Again. If the error '
                        . 'persists please contact Bold Support.'
                    ),
                    $e
                );
            }
        }
    }

    /**
     * Add Magento domain to the CORS allow list.
     *
     * @param int $websiteId
     * @return void
     */
    private function addDomainToCorsAllowList(int $websiteId)
    {
        $magentoUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);
        $this->addDomainToCorsAllowList->addDomain($websiteId, $magentoUrl);
    }
}
