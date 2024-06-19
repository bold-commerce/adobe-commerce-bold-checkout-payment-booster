<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Checkout;

use Bold\Checkout\Model\Config;
use Bold\Checkout\Model\ConfigInterface;
use Bold\CheckoutPaymentBooster\Model\Config as PaymentBoosterConfig;
use Bold\CheckoutPaymentBooster\Model\PaymentStyleManagement\PaymentStyleBuilderFactory;
use Bold\CheckoutPaymentBooster\Api\PaymentStyleManagementInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Observe 'admin_system_config_changed_section_checkout' event and sync payment iframe styles.
 */
class SyncPaymentStyle implements ObserverInterface
{
    /**
     * @var ConfigInterface
     */
    private $config;

    /**
     * @var PaymentBoosterConfig
     */
    private $paymentBoosterConfig;

    /**
     * @var PaymentStyleManagementInterface
     */
    private $paymentStyleManagement;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var PaymentStyleBuilderFactory
     */
    private $paymentStyleBuilderFactory;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param ConfigInterface $config
     * @param PaymentBoosterConfig $paymentBoosterConfig
     * @param PaymentStyleManagementInterface $paymentStyleManagement
     * @param StoreManagerInterface $storeManager
     * @param PaymentStyleBuilderFactory $paymentStyleBuilderFactory
     * @param SerializerInterface $serializer
     */
    public function __construct(
        ConfigInterface                 $config,
        PaymentBoosterConfig            $paymentBoosterConfig,
        PaymentStyleManagementInterface $paymentStyleManagement,
        StoreManagerInterface           $storeManager,
        PaymentStyleBuilderFactory      $paymentStyleBuilderFactory,
        SerializerInterface             $serializer
    ) {
        $this->config = $config;
        $this->paymentBoosterConfig = $paymentBoosterConfig;
        $this->paymentStyleManagement = $paymentStyleManagement;
        $this->storeManager = $storeManager;
        $this->paymentStyleBuilderFactory = $paymentStyleBuilderFactory;
        $this->serializer = $serializer;
    }

    /**
     * Sync payment iframe styles.
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();

        $websiteId = (int)$event->getWebsite() ?: (int)$this->storeManager->getWebsite(true)->getId();
        if (!$this->config->isCheckoutEnabled($websiteId)
            || !in_array(PaymentBoosterConfig::PATH_PAYMENT_CSS, $event->getChangedPaths())
        ) {
            return;
        }

        $style = preg_replace(
            '/\s+/',
            ' ',
            $this->serializer->unserialize($this->paymentBoosterConfig->getPaymentCss($websiteId))
        );
        if (!empty($style)) {
            $styleBuilder = $this->paymentStyleBuilderFactory->create();
            $styleBuilder->addCssRule($style);
            $this->paymentStyleManagement->update($websiteId, $styleBuilder->build());
        } else {
            $this->paymentStyleManagement->delete($websiteId);
        }
    }
}
