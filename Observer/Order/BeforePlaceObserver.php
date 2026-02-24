<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Order;

use Bold\CheckoutPaymentBooster\Api\Data\MagentoQuoteBoldOrderInterface;
use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterfaceFactory;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\InitOrderFromQuote;
use Bold\CheckoutPaymentBooster\Model\MagentoQuoteBoldOrder;
use Bold\CheckoutPaymentBooster\Model\Order\CheckPaymentMethod;
use Bold\CheckoutPaymentBooster\Model\Order\HydrateOrderFromQuote;
use Bold\CheckoutPaymentBooster\Model\Payment\Authorize;
use Bold\CheckoutPaymentBooster\Model\ResumeOrder;
use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order\Payment;
use Psr\Log\LoggerInterface;

/**
 * Authorize Bold payments before placing order.
 */
class BeforePlaceObserver implements ObserverInterface
{
    /**
     * @var Authorize
     */
    private $authorize;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var CheckoutData
     */
    private $checkoutData;

    /**
     * @var HydrateOrderFromQuote
     */
    private $hydrateOrderFromQuote;

    /**
     * @var CheckPaymentMethod
     */
    private $checkPaymentMethod;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /** @var MagentoQuoteBoldOrderRepositoryInterface */
    private $magentoQuoteBoldOrderRepository;

    /** @var LoggerInterface */
    private $logger;

    /** @var InitOrderFromQuote */
    private $initOrderFromQuote;

    /** @var ResumeOrder */
    private $resumeOrder;

    /** @var CheckoutSession */
    private $checkoutSession;

    /**
     * @param Authorize $authorize
     * @param CartRepositoryInterface $cartRepository
     * @param CheckoutData $checkoutData
     * @param HydrateOrderFromQuote $hydrateOrderFromQuote
     * @param CheckPaymentMethod $checkPaymentMethod
     * @param SerializerInterface $serializer
     * @param MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository
     * @param LoggerInterface $logger
     * @param InitOrderFromQuote $initOrderFromQuote
     * @param ResumeOrder $resumeOrder
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        Authorize $authorize,
        CartRepositoryInterface $cartRepository,
        CheckoutData $checkoutData,
        HydrateOrderFromQuote $hydrateOrderFromQuote,
        CheckPaymentMethod $checkPaymentMethod,
        SerializerInterface $serializer,
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository,
        LoggerInterface $logger,
        InitOrderFromQuote $initOrderFromQuote,
        ResumeOrder $resumeOrder,
        CheckoutSession $checkoutSession
    ) {
        $this->authorize = $authorize;
        $this->cartRepository = $cartRepository;
        $this->checkoutData = $checkoutData;
        $this->hydrateOrderFromQuote = $hydrateOrderFromQuote;
        $this->checkPaymentMethod = $checkPaymentMethod;
        $this->serializer = $serializer;
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
        $this->logger = $logger;
        $this->initOrderFromQuote = $initOrderFromQuote;
        $this->resumeOrder = $resumeOrder;
        $this->checkoutSession = $checkoutSession;
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
            $this->logger->info('Order is not a Bold order');
            return;
        }
        $quoteId = $order->getQuoteId();
        /** @var CartInterface&Quote $quote */
        $quote = $this->cartRepository->get($quoteId);
        $websiteId = (int)$quote->getStore()->getWebsiteId();

        // Resolve publicOrderId: extension attribute → session → DB relation.
        $publicOrderId = $quote->getExtensionAttributes()->getBoldOrderId()
            ?? $this->checkoutData->getPublicOrderId();

        if (!$publicOrderId) {
            try {
                $relation = $this->magentoQuoteBoldOrderRepository->getByQuoteId((string) $quoteId);
                $publicOrderId = $relation->getBoldOrderId() ?: null;
            } catch (NoSuchEntityException $e) {
                // No relation record yet — will be created below.
            }
        }

        // If publicOrderId is still missing the Bold session was lost (expired, cleared, or never
        // initialized). Attempt recovery before proceeding to hydrate and authorize.
        if (!$publicOrderId) {
            $publicOrderId = $this->recoverBoldSession($quote, $websiteId);
        } else {
            // publicOrderId is present — try to resume to get a fresh JWT for the frontend.
            // If resume fails we re-initialize so hydrate and authorize always work with a live order.
            $publicOrderId = $this->refreshBoldSession($publicOrderId, $quote, $websiteId);
        }

        if ($publicOrderId && $quoteId) {
            $this->magentoQuoteBoldOrderRepository->saveBoldQuotePublicOrderRelation($publicOrderId, (string) $quoteId);
        }

        $this->hydrateOrderFromQuote->hydrate($quote, $publicOrderId);

        // Ordering guard: hydrate must complete and persist its timestamp before authorization.
        // saveHydratedAt() is called inside hydrate() but uses a silent try-catch internally.
        // If the DB write failed silently we catch it here and block authorization.
        $relationAfterHydrate = $this->magentoQuoteBoldOrderRepository->getByQuoteId((string) $quoteId);
        if ($relationAfterHydrate->getSuccessfulHydrateAt() === null) {
            throw new LocalizedException(
                __(
                    'Cannot authorize Bold payment: order hydration did not complete for quote %1.',
                    $quoteId
                )
            );
        }

        $transactionData = $this->authorize->execute($publicOrderId, $websiteId, (string) $quoteId);
        $this->saveTransactionData($order, $transactionData);
    }

    /**
     * No publicOrderId anywhere — create a brand-new Bold order for this quote.
     *
     * Stores the full order data in the checkout session so the frontend also receives
     * fresh credentials (publicOrderId, jwtToken, payment_gateways) on the next response.
     *
     * @throws LocalizedException
     */
    private function recoverBoldSession(Quote $quote, int $websiteId): string
    {
        $this->logger->info(sprintf(
            '[Bold][BeforePlaceObserver] publicOrderId missing for quote %s — initializing new Bold order.',
            $quote->getId()
        ));

        try {
            $orderData = $this->initOrderFromQuote->init($quote);
        } catch (Exception $e) {
            throw new LocalizedException(__(
                'Cannot place order: failed to initialize Bold payment session for quote %1. Error: %2',
                $quote->getId(),
                $e->getMessage()
            ));
        }

        $publicOrderId = $orderData['data']['public_order_id'] ?? null;

        if (!$publicOrderId) {
            throw new LocalizedException(__(
                'Cannot place order: Bold payment session could not be established for quote %1.',
                $quote->getId()
            ));
        }

        $this->checkoutSession->setBoldCheckoutData($orderData);

        $this->logger->info(sprintf(
            '[Bold][BeforePlaceObserver] New Bold order initialized. publicOrderId=%s for quote %s.',
            $publicOrderId,
            $quote->getId()
        ));

        return $publicOrderId;
    }

    /**
     * publicOrderId is known — resume it to get a fresh JWT and confirm the order is still live.
     *
     * If resume fails (order expired or was cancelled on Bold's side) falls back to creating a
     * new order via recoverBoldSession() so hydrate and authorize always work.
     */
    private function refreshBoldSession(string $publicOrderId, Quote $quote, int $websiteId): string
    {
        $resumeData = $this->resumeOrder->resume($publicOrderId, $websiteId);

        if ($resumeData) {
            // Order is live — update the JWT in the session so the frontend gets fresh credentials.
            $sessionData = $this->checkoutSession->getBoldCheckoutData() ?? [];
            $sessionData['data']['jwt_token'] = $resumeData['data']['jwt_token'];
            $this->checkoutSession->setBoldCheckoutData($sessionData);

            $this->logger->info(sprintf(
                '[Bold][BeforePlaceObserver] Resumed Bold order %s for quote %s. JWT refreshed.',
                $publicOrderId,
                $quote->getId()
            ));

            return $publicOrderId;
        }

        // Resume returned empty — the Bold order is no longer valid. Create a new one.
        $this->logger->info(sprintf(
            '[Bold][BeforePlaceObserver] Resume failed for publicOrderId=%s (quote %s) — re-initializing.',
            $publicOrderId,
            $quote->getId()
        ));

        return $this->recoverBoldSession($quote, $websiteId);
    }

    /**
     * Add Bold transaction data to order payment.
     *
     * @param OrderInterface $order
     * @param array{
     *     data: array{
     *         transactions: array<array{
     *             transaction_id: string,
     *             tender_details: array{
     *                 account: string,
     *                 email: string
     *             }
     *         }>
     *     }
     * } $transactionData
     * @return void
     * @throws LocalizedException
     */
    private function saveTransactionData(OrderInterface $order, array $transactionData)
    {
        $transactionId = $transactionData['data']['transactions'][0]['transaction_id'] ?? null;
        if (!$transactionId) {
            throw new LocalizedException(
                __('Bold payment authorization succeeded but returned no transaction ID. The order cannot be placed.')
            );
        }

        /** @var OrderPaymentInterface&Payment $orderPayment */
        $orderPayment = $order->getPayment();

        $orderPayment->setTransactionId($transactionId);
        $orderPayment->setIsTransactionClosed(false);
        $orderPayment->addTransaction(TransactionInterface::TYPE_AUTH);
        $cardDetails = $transactionData['data']['transactions'][0]['tender_details'] ?? null;
        if ($cardDetails) {
            $orderPayment->setAdditionalInformation('card_details', $this->serializer->serialize($cardDetails));
        }
    }
}
