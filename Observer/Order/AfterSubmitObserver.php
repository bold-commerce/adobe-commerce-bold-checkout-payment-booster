<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Order;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Order\CheckPaymentMethod;
use Bold\CheckoutPaymentBooster\Model\Order\SetCompleteState;
use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
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
     * @var OrderExtensionDataRepository
     */
    private $orderExtensionDataRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var MagentoQuoteBoldOrderRepositoryInterface */
    private $magentoQuoteBoldOrderRepository;

    /**
     * Constructor method.
     *
     * @param CheckoutData $checkoutData Instance of CheckoutData.
     * @param SetCompleteState $setCompleteState Instance of SetCompleteState.
     * @param CheckPaymentMethod $checkPaymentMethod Instance of CheckPaymentMethod.
     * @param OrderExtensionDataRepository $orderExtensionDataRepository Repository for order extension data.
     * @param LoggerInterface $logger Logger instance for handling logs.
     * @param MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository Interface for managing
     * Magento Quote Bold Order Repository.
     * @return void
     */
    public function __construct(
        CheckoutData $checkoutData,
        SetCompleteState $setCompleteState,
        CheckPaymentMethod $checkPaymentMethod,
        OrderExtensionDataRepository $orderExtensionDataRepository,
        LoggerInterface $logger,
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository
    ) {
        $this->checkoutData = $checkoutData;
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
        $this->setCompleteState = $setCompleteState;
        $this->checkPaymentMethod = $checkPaymentMethod;
        $this->logger = $logger;
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
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
        $order = $observer->getEvent()->getOrder();
        if (!$order || !$this->checkPaymentMethod->isBold($order)) {
            return;
        }

        $orderId = $order->getEntityId();
        // Skip if Magento order does Not have an ID yet
        // or skip if the Bold Order Quote Relation has already a successful State call timestamp
        if (!$orderId || $this->magentoQuoteBoldOrderRepository->isBoldOrderProcessed($order)) {
            return;
        }
        
        // Check if this is an integration cart
        $isBoldIntegrationCart = $order->getExtensionAttributes() 
            && $order->getExtensionAttributes()->getIsBoldIntegrationCart();

        $publicOrderId = $this->checkoutData->getPublicOrderId();

        if ($publicOrderId !== null) {
            $this->checkoutData->resetCheckoutData();
        }

        if (!$publicOrderId) {
            // If missing Public order id, try to get from the Bold Order Quote relation
            $publicOrderId = $this->magentoQuoteBoldOrderRepository->getPublicOrderIdFromOrder($order);
        }

        // Load existing record or create new one (idempotent pattern)
        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId((int) $orderId);
        
        // If no existing record, set the order_id for the new record
        if (!$orderExtensionData->getId()) {
            $orderExtensionData->setOrderId((int) $orderId);
        }

        // Update public order id if present and not already set
        if ($publicOrderId !== null && !$orderExtensionData->getPublicId()) {
            $orderExtensionData->setPublicId($publicOrderId);
        }

        // Set is_bold_integration_cart flag if present and not already set to true
        if ($isBoldIntegrationCart && !$orderExtensionData->getIsBoldIntegrationCart()) {
            $orderExtensionData->setIsBoldIntegrationCart(true);
        }

        try {
            $this->orderExtensionDataRepository->save($orderExtensionData);
        } catch (Exception $e) {
            $this->logger->critical($e);
            return;
        }
        
        // Skip state call for integration orders
        // Bold Checkout will handle the capture process for these orders
        if ($isBoldIntegrationCart) {
            return;
        }
        
        $this->setCompleteState->execute($order);
    }
}
