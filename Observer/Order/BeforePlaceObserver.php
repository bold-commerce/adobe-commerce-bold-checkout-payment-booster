<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Order;

use Bold\CheckoutPaymentBooster\Api\Data\MagentoQuoteBoldOrderInterface;
use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterfaceFactory;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Log\CheckoutOrderTracer;
use Bold\CheckoutPaymentBooster\Model\MagentoQuoteBoldOrder;
use Bold\CheckoutPaymentBooster\Model\Order\CheckPaymentMethod;
use Bold\CheckoutPaymentBooster\Model\Order\HydrateOrderFromQuote;
use Bold\CheckoutPaymentBooster\Model\Payment\Authorize;
use Exception;
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

    /** @var CheckoutOrderTracer */
    private $checkoutOrderTracer;

    /**
     * @param Authorize $authorize
     * @param CartRepositoryInterface $cartRepository
     * @param CheckoutData $checkoutData
     * @param HydrateOrderFromQuote $hydrateOrderFromQuote
     * @param CheckPaymentMethod $checkPaymentMethod
     * @param SerializerInterface $serializer
     * @param MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository
     * @param CheckoutOrderTracer $checkoutOrderTracer
     */
    public function __construct(
        Authorize $authorize,
        CartRepositoryInterface $cartRepository,
        CheckoutData $checkoutData,
        HydrateOrderFromQuote $hydrateOrderFromQuote,
        CheckPaymentMethod $checkPaymentMethod,
        SerializerInterface $serializer,
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository,
        CheckoutOrderTracer $checkoutOrderTracer
    ) {
        $this->authorize = $authorize;
        $this->cartRepository = $cartRepository;
        $this->checkoutData = $checkoutData;
        $this->hydrateOrderFromQuote = $hydrateOrderFromQuote;
        $this->checkPaymentMethod = $checkPaymentMethod;
        $this->serializer = $serializer;
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
        $this->checkoutOrderTracer = $checkoutOrderTracer;
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
        $quoteId = $order->getQuoteId();
        /** @var CartInterface&Quote $quote */
        $quote = $this->cartRepository->get($quoteId);
        $publicOrderIdFromQuote = null;
        $extensionAttributes = $quote->getExtensionAttributes();
        if ($extensionAttributes !== null) {
            $publicOrderIdFromQuote = $extensionAttributes->getBoldOrderId();
        }
        $publicOrderIdFromSession = $this->checkoutData->getPublicOrderId();
        $publicOrderIdFromDb = null;
        try {
            $publicOrderIdFromDb = $this->magentoQuoteBoldOrderRepository
                ->getByQuoteId((string) $quoteId)
                ->getBoldOrderId();
        } catch (NoSuchEntityException $e) {
            // No persisted quote ↔ Bold order relation yet.
        }
        $publicOrderId = $publicOrderIdFromQuote ?? $publicOrderIdFromSession;

        $this->checkoutOrderTracer->trace('before_place_order', [
            'quote_id' => $quoteId,
            'customer_id' => $quote->getCustomerId(),
            'payment_method' => $order->getPayment()->getMethod(),
            'grand_total' => $quote->getGrandTotal(),
            'currency' => $quote->getQuoteCurrencyCode(),
            'selected_public_order_id' => $publicOrderId,
            'public_order_id_quote_ext' => $publicOrderIdFromQuote,
            'public_order_id_session' => $publicOrderIdFromSession,
            'public_order_id_db' => $publicOrderIdFromDb,
            'public_order_ids_match' => $this->publicOrderIdsMatch(
                $publicOrderId,
                $publicOrderIdFromQuote,
                $publicOrderIdFromSession,
                $publicOrderIdFromDb
            ),
        ]);

        if ($publicOrderId && $quoteId) {
            $this->magentoQuoteBoldOrderRepository->saveBoldQuotePublicOrderRelation($publicOrderId, (string) $quoteId);
        }

        $websiteId = (int)$quote->getStore()->getWebsiteId();
        $this->hydrateOrderFromQuote->hydrate($quote, $publicOrderId);
        $transactionData = $this->authorize->execute($publicOrderId, $websiteId, (int) $quoteId);
        $this->saveTransactionData($order, $transactionData);
        $this->magentoQuoteBoldOrderRepository->saveAuthorizedAt((string) $quoteId);
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
            return;
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

    /**
     * @param string|null $selected
     * @param string|null $fromQuote
     * @param string|null $fromSession
     * @param string|null $fromDb
     * @return bool
     */
    private function publicOrderIdsMatch(
        ?string $selected,
        ?string $fromQuote,
        ?string $fromSession,
        ?string $fromDb
    ): bool {
        $ids = array_values(array_filter([$selected, $fromQuote, $fromSession, $fromDb], static function ($id) {
            return $id !== null && $id !== '';
        }));

        if (count($ids) <= 1) {
            return true;
        }

        return count(array_unique($ids)) === 1;
    }
}
