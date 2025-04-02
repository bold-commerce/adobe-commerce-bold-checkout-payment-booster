<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Order;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
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
class AfterSubmitObserver implements ObserverInterface
{
    /**
     * @var CheckoutData
     */
    private $checkoutData;

    /**
     * @var SetCompleteState
     */
    private $setCompleteState;

    /**
     * @var CheckPaymentMethod
     */
    private $checkPaymentMethod;

    /**
     * @var OrderExtensionDataFactory
     */
    private $orderExtensionDataFactory;

    /**
     * @var OrderExtensionDataResource
     */
    private $orderExtensionDataResource;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param CheckoutData $checkoutData
     * @param SetCompleteState $setCompleteState
     * @param CheckPaymentMethod $checkPaymentMethod
     * @param OrderExtensionDataFactory $orderExtensionDataFactory
     * @param OrderExtensionDataResource $orderExtensionDataResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        CheckoutData $checkoutData,
        SetCompleteState $setCompleteState,
        CheckPaymentMethod $checkPaymentMethod,
        OrderExtensionDataFactory $orderExtensionDataFactory,
        OrderExtensionDataResource $orderExtensionDataResource,
        LoggerInterface $logger
    ) {
        $this->checkoutData = $checkoutData;
        $this->orderExtensionDataFactory = $orderExtensionDataFactory;
        $this->orderExtensionDataResource = $orderExtensionDataResource;
        $this->setCompleteState = $setCompleteState;
        $this->checkPaymentMethod = $checkPaymentMethod;
        $this->logger = $logger;
    }

    /**
     * Authorize Bold payments before placing order.
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer): void
    {
        $publicOrderId = $this->checkoutData->getPublicOrderId();

        if ($publicOrderId !== null) {
            $this->checkoutData->resetCheckoutData();
        }

        $order = $observer->getEvent()->getOrder();
        if (!$order || !$this->checkPaymentMethod->isBold($order)) {
            return;
        }
        $orderId = (int)$order->getEntityId();
        $orderExtensionData = $this->orderExtensionDataFactory->create();
        $orderExtensionData->setOrderId($orderId);

        if ($publicOrderId !== null) {
            $orderExtensionData->setPublicId($publicOrderId);
        }

        try {
            $this->orderExtensionDataResource->save($orderExtensionData);
        } catch (Exception $e) {
            $this->logger->critical($e);
            return;
        }
        $this->setCompleteState->execute($order);
    }
}
