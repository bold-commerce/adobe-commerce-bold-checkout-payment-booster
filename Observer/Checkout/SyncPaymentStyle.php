<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Checkout;

use Bold\CheckoutPaymentBooster\Api\PaymentStyleManagementInterface;
use Bold\CheckoutPaymentBooster\Model\Config as PaymentBoosterConfig;
use Bold\CheckoutPaymentBooster\Model\GetDefaultPaymentCss;
use Bold\CheckoutPaymentBooster\Model\PaymentStyleManagement\PaymentStyleBuilderFactory;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Observe 'admin_system_config_changed_section_checkout' event and sync payment iframe styles.
 */
class SyncPaymentStyle implements ObserverInterface
{
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var GetDefaultPaymentCss
     */
    private $getDefaultPaymentCss;

    /**
     * @param PaymentBoosterConfig $paymentBoosterConfig
     * @param PaymentStyleManagementInterface $paymentStyleManagement
     * @param StoreManagerInterface $storeManager
     * @param PaymentStyleBuilderFactory $paymentStyleBuilderFactory
     * @param SerializerInterface $serializer
     * @param GetDefaultPaymentCss $getDefaultPaymentCss
     * @param LoggerInterface $logger
     */
    public function __construct(
        PaymentBoosterConfig $paymentBoosterConfig,
        PaymentStyleManagementInterface $paymentStyleManagement,
        StoreManagerInterface $storeManager,
        PaymentStyleBuilderFactory $paymentStyleBuilderFactory,
        SerializerInterface $serializer,
        GetDefaultPaymentCss $getDefaultPaymentCss,
        LoggerInterface $logger
    ) {
        $this->paymentBoosterConfig = $paymentBoosterConfig;
        $this->paymentStyleManagement = $paymentStyleManagement;
        $this->storeManager = $storeManager;
        $this->paymentStyleBuilderFactory = $paymentStyleBuilderFactory;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->getDefaultPaymentCss = $getDefaultPaymentCss;
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
        if (!$this->paymentBoosterConfig->isPaymentBoosterEnabled($websiteId)) {
            return;
        }
        try {
            $savedValue = $this->paymentBoosterConfig->getPaymentCss($websiteId);
            $newStyle = $savedValue
                ? preg_replace('/\s+/', ' ', $this->serializer->unserialize($savedValue))
                : $this->getDefaultPaymentCss->getCss();
            $styleBuilder = $this->paymentStyleBuilderFactory->create();
            $savedStyles = $this->paymentStyleManagement->get($websiteId);
            $oldStyle = $savedStyles['css_rules'][0]['cssText'] ?? '';
            if ($oldStyle !== $newStyle) {
                $styleBuilder->addCssRule($newStyle);
                $this->paymentStyleManagement->update($websiteId, $styleBuilder->build());
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
