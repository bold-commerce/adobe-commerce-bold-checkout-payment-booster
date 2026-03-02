<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Order;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\InitOrderFromQuote;
use Bold\CheckoutPaymentBooster\Model\Order\CheckPaymentMethod;
use Bold\CheckoutPaymentBooster\Model\Order\HydrateOrderFromQuote;
use Bold\CheckoutPaymentBooster\Model\Payment\Authorize;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
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

    /**
     * @param Authorize $authorize
     * @param CartRepositoryInterface $cartRepository
     * @param CheckoutData $checkoutData
     * @param HydrateOrderFromQuote $hydrateOrderFromQuote
     * @param CheckPaymentMethod $checkPaymentMethod
     * @param SerializerInterface $serializer
     * @param MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Authorize $authorize,
        CartRepositoryInterface $cartRepository,
        CheckoutData $checkoutData,
        HydrateOrderFromQuote $hydrateOrderFromQuote,
        CheckPaymentMethod $checkPaymentMethod,
        SerializerInterface $serializer,
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository,
        LoggerInterface $logger
    ) {
        $this->authorize = $authorize;
        $this->cartRepository = $cartRepository;
        $this->checkoutData = $checkoutData;
        $this->hydrateOrderFromQuote = $hydrateOrderFromQuote;
        $this->checkPaymentMethod = $checkPaymentMethod;
        $this->serializer = $serializer;
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
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
