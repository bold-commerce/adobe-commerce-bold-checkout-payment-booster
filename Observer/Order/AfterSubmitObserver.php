<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Order;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Order\CheckPaymentMethod;
use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionDataFactory;
use Bold\CheckoutPaymentBooster\Model\Order\SetCompleteState;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\Order\OrderExtensionData as OrderExtensionDataResource;
use Exception;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
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

    /** @var MagentoQuoteBoldOrderRepositoryInterface */
    private $magentoQuoteBoldOrderRepository;

    /**
     * Constructor method.
     *
     * @param CheckoutData $checkoutData Instance of CheckoutData.
     * @param SetCompleteState $setCompleteState Instance of SetCompleteState.
     * @param CheckPaymentMethod $checkPaymentMethod Instance of CheckPaymentMethod.
     * @param OrderExtensionDataFactory $orderExtensionDataFactory Instance of OrderExtensionDataFactory.
     * @param OrderExtensionDataResource $orderExtensionDataResource Instance of OrderExtensionDataResource.
     * @param LoggerInterface $logger Logger instance for handling logs.
     * @param MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository Interface for managing
     * Magento Quote Bold Order Repository.
     * @return void
     */
    public function __construct(
        CheckoutData $checkoutData,
        SetCompleteState $setCompleteState,
        CheckPaymentMethod $checkPaymentMethod,
        OrderExtensionDataFactory $orderExtensionDataFactory,
        OrderExtensionDataResource $orderExtensionDataResource,
        LoggerInterface $logger,
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository
    ) {
        $this->checkoutData = $checkoutData;
        $this->orderExtensionDataFactory = $orderExtensionDataFactory;
        $this->orderExtensionDataResource = $orderExtensionDataResource;
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
        if (!$orderId || $this->magentoQuoteBoldOrderRepository->isBoldOrderProcessed($order)) {
            return;
        }

        // Capture the session value first so we know whether to clear it after all work completes.
        $publicOrderIdFromSession = $this->checkoutData->getPublicOrderId();
        $publicOrderId = $publicOrderIdFromSession;

        if (!$publicOrderId) {
            $publicOrderId = $this->magentoQuoteBoldOrderRepository->getPublicOrderIdFromOrder($order);
        }

        if (!$publicOrderId) {
            $this->logger->critical(sprintf(
                '[Bold][AfterSubmitObserver] publicOrderId is null for order %s (quote %s). '
                . 'Both session and DB fallback returned nothing. SetCompleteState will be skipped.',
                $orderId,
                $order->getQuoteId()
            ));
            return;
        }

        $orderExtensionData = $this->orderExtensionDataFactory->create();
        $orderExtensionData->setOrderId((int) $orderId);
        $orderExtensionData->setPublicId($publicOrderId);

        try {
            $this->orderExtensionDataResource->save($orderExtensionData);
        } catch (Exception $e) {
            $this->logger->critical($e);
            return;
        }

        // SetCompleteState::execute() throws LocalizedException when authorization has not been
        // recorded. The order is already committed, so we must not let the exception propagate —
        // it would cause a 500 response after a successful order save.
        try {
            $this->setCompleteState->execute($order);
        } catch (LocalizedException $e) {
            $this->logger->critical($e);
        }

        // Reset the session only after all work is complete and only if the publicOrderId
        // actually came from the session (not the DB fallback).
        if ($publicOrderIdFromSession !== null) {
            $this->checkoutData->resetCheckoutData();
        }
    }
}
