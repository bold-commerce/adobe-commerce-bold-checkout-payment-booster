<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Order;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterfaceFactory;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\Order\CheckPaymentMethod;
use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionDataFactory;
use Bold\CheckoutPaymentBooster\Model\Order\SetCompleteState;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\Order\OrderExtensionData as OrderExtensionDataResource;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Authorize Bold payments before placing order.
 */
class FallbackAfterSubmitObserver extends AfterSubmitObserver implements ObserverInterface
{
    /** @var Config */
    private $config;

    /** @var LoggerInterface */
    private $logger;

    /**
     * Constructor
     *
     * @param CheckoutData $checkoutData
     * @param SetCompleteState $setCompleteState
     * @param CheckPaymentMethod $checkPaymentMethod
     * @param OrderExtensionDataFactory $orderExtensionDataFactory
     * @param OrderExtensionDataResource $orderExtensionDataResource
     * @param LoggerInterface $logger
     * @param MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository
     * @param Config $config
     */
    public function __construct(
        CheckoutData $checkoutData,
        SetCompleteState $setCompleteState,
        CheckPaymentMethod $checkPaymentMethod,
        OrderExtensionDataFactory $orderExtensionDataFactory,
        OrderExtensionDataResource $orderExtensionDataResource,
        LoggerInterface $logger,
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository,
        Config $config
    ) {
        $this->config = $config;
        $this->logger = $logger;

        parent::__construct(
            $checkoutData,
            $setCompleteState,
            $checkPaymentMethod,
            $orderExtensionDataFactory,
            $orderExtensionDataResource,
            $logger,
            $magentoQuoteBoldOrderRepository
        );
    }

    /**
     * Execute the observer logic for the provided event.
     *
     * @param Observer $observer The observer instance that contains the event data.
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        $websiteId = (int) $order->getStore()->getWebsiteId();
        if ($this->config->useFallbackObserver($websiteId)) try {
            parent::execute($observer);
        } catch (NoSuchEntityException $e) {
        } catch (LocalizedException $e) {
            $this->logger->critical($e);
        }
    }
}
