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
        // Skip if Magento order does Not have an ID yet
        // or skip if the Bold Order Quote Relation has already a successful State call timestamp
        if (!$orderId || $this->magentoQuoteBoldOrderRepository->isBoldOrderProcessed($order)) {
            return;
        }

        $alreadyProcessed = $this->magentoQuoteBoldOrderRepository->isBoldOrderProcessed($order);
        // @phpstan-ignore if.alwaysFalse
        if ($alreadyProcessed) {
            $this->logger->info(sprintf(
                '[Bold][AfterSubmitObserver] Skipped: order %s (quote %s) already has a successful_state_at timestamp — likely processed by an earlier observer invocation.',
                $orderId,
                $order->getQuoteId()
            ));
            return;
        }

        $publicOrderIdFromSession = $this->checkoutData->getPublicOrderId();

        $this->logger->info(sprintf(
            '[Bold][AfterSubmitObserver] publicOrderId from session: %s (order %s, quote %s)',
            $publicOrderIdFromSession ?? 'null — session is empty or was already reset',
            $orderId,
            $order->getQuoteId()
        ));

        $publicOrderId = $publicOrderIdFromSession;

        if (!$publicOrderId) {
            $this->logger->info(sprintf(
                '[Bold][AfterSubmitObserver] Session was empty — falling back to bold_booster_magento_quote_bold_order table for order %s (quote %s).',
                $orderId,
                $order->getQuoteId()
            ));
            $publicOrderId = $this->magentoQuoteBoldOrderRepository->getPublicOrderIdFromOrder($order);
            $this->logger->info(sprintf(
                '[Bold][AfterSubmitObserver] publicOrderId from DB fallback: %s',
                $publicOrderId ?? 'null — no relation record found'
            ));
        }

        if (!$publicOrderId) {
            $this->logger->critical(sprintf(
                '[Bold][AfterSubmitObserver] publicOrderId is null for order %s (quote %s). '
                . 'Both session and DB fallback returned nothing. '
                . 'Bold order may be unlinked or the session was never initialised. '
                . 'SetCompleteState will be skipped. Check BeforePlaceObserver and InitializeBoldOrderObserver logs.',
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

        // SetCompleteState::execute() now throws LocalizedException when authorization has not
        // been recorded (auth-before-setState ordering guard). The order is already committed to
        // the database at this point, so we must not let the exception propagate — it would
        // cause a 500 response after a successful order save. Log at critical so the team is
        // alerted; the SuccessPlugin will handle the customer-facing redirect if needed.
        try {
            $this->setCompleteState->execute($order);
        } catch (LocalizedException $e) {
            $this->logger->critical($e);
        }

        // Reset session data only after all work is complete. Clearing the session early would
        // prevent retry paths (e.g. FallbackAfterSubmitObserver) from finding the publicOrderId
        // in the session if any of the steps above failed.
        if ($publicOrderIdFromSession !== null) {
            $this->checkoutData->resetCheckoutData();
            $this->logger->info(sprintf(
                '[Bold][AfterSubmitObserver] resetCheckoutData() called for order %s. Session cleared.',
                $orderId
            ));
        }
    }
}
