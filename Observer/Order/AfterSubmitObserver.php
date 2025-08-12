<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Order;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterfaceFactory;
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
use Magento\Sales\Api\Data\OrderInterface;
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

    /** @var MagentoQuoteBoldOrderRepositoryInterfaceFactory */
    private $magentoQuoteBoldOrderRepositoryFactory;

    /**
     * @param CheckoutData $checkoutData
     * @param SetCompleteState $setCompleteState
     * @param CheckPaymentMethod $checkPaymentMethod
     * @param OrderExtensionDataFactory $orderExtensionDataFactory
     * @param OrderExtensionDataResource $orderExtensionDataResource
     * @param LoggerInterface $logger
     * @param MagentoQuoteBoldOrderRepositoryInterfaceFactory $magentoQuoteBoldOrderRepositoryFactory
     */
    public function __construct(
        CheckoutData $checkoutData,
        SetCompleteState $setCompleteState,
        CheckPaymentMethod $checkPaymentMethod,
        OrderExtensionDataFactory $orderExtensionDataFactory,
        OrderExtensionDataResource $orderExtensionDataResource,
        LoggerInterface $logger,
        MagentoQuoteBoldOrderRepositoryInterfaceFactory $magentoQuoteBoldOrderRepositoryFactory
    ) {
        $this->checkoutData = $checkoutData;
        $this->orderExtensionDataFactory = $orderExtensionDataFactory;
        $this->orderExtensionDataResource = $orderExtensionDataResource;
        $this->setCompleteState = $setCompleteState;
        $this->checkPaymentMethod = $checkPaymentMethod;
        $this->logger = $logger;
        $this->magentoQuoteBoldOrderRepositoryFactory = $magentoQuoteBoldOrderRepositoryFactory;
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
        if (!$orderId || $this->isBoldOrderProcessed($order)) {
            return;
        }

        $publicOrderId = $this->checkoutData->getPublicOrderId();

        if ($publicOrderId !== null) {
            $this->checkoutData->resetCheckoutData();
        }

        if (!$publicOrderId) {
            // If missing Public order id, try to get from the Bold Order Quote relation
            $publicOrderId = $this->getPublicOrderIdFromQuote($order);
        }

        $orderExtensionData = $this->orderExtensionDataFactory->create();
        $orderExtensionData->setOrderId((int) $orderId);

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

    /**
     * Check if the order has successful State call.
     *
     * @param OrderInterface $order
     * @return bool
     */
    private function isBoldOrderProcessed(OrderInterface $order): bool
    {
        $repository = $this->magentoQuoteBoldOrderRepositoryFactory->create();
        $quoteId = $order->getQuoteId();
        return $repository->isQuoteProcessed((string) $quoteId);
    }

    /**
     * Get Bold Public Order Id from Quote.
     *
     * @param OrderInterface $order
     * @return null|string
     */
    private function getPublicOrderIdFromQuote(OrderInterface $order): ?string
    {
        $repository = $this->magentoQuoteBoldOrderRepositoryFactory->create();
        $quoteId = $order->getQuoteId();
        try {
            $magentoQuoteBoldOrder = $repository->getByQuoteId((string) $quoteId);
            return $magentoQuoteBoldOrder->getBoldOrderId();
        } catch (NoSuchEntityException $e) {
            return null;
        }

    }
}
