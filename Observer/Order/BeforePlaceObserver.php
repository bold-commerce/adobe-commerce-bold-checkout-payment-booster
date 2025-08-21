<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Order;

use Bold\CheckoutPaymentBooster\Api\Data\MagentoQuoteBoldOrderInterface;
use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterfaceFactory;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
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

    /** @var MagentoQuoteBoldOrderRepositoryInterfaceFactory */
    private $magentoQuoteBoldOrderRepositoryFactory;

    /** @var LoggerInterface */
    private $logger;

    /** @var TimezoneInterface */
    private $timezoneInterface;

    /**
     * @param Authorize $authorize
     * @param CartRepositoryInterface $cartRepository
     * @param CheckoutData $checkoutData
     * @param HydrateOrderFromQuote $hydrateOrderFromQuote
     * @param CheckPaymentMethod $checkPaymentMethod
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     * @param MagentoQuoteBoldOrderRepositoryInterfaceFactory $magentoQuoteBoldOrderRepositoryFactory
     * @param TimezoneInterface $timezoneInterface
     */
    public function __construct(
        Authorize $authorize,
        CartRepositoryInterface $cartRepository,
        CheckoutData $checkoutData,
        HydrateOrderFromQuote $hydrateOrderFromQuote,
        CheckPaymentMethod $checkPaymentMethod,
        SerializerInterface $serializer,
        LoggerInterface $logger,
        MagentoQuoteBoldOrderRepositoryInterfaceFactory $magentoQuoteBoldOrderRepositoryFactory,
        TimezoneInterface $timezoneInterface
    ) {
        $this->authorize = $authorize;
        $this->cartRepository = $cartRepository;
        $this->checkoutData = $checkoutData;
        $this->hydrateOrderFromQuote = $hydrateOrderFromQuote;
        $this->checkPaymentMethod = $checkPaymentMethod;
        $this->serializer = $serializer;
        $this->magentoQuoteBoldOrderRepositoryFactory = $magentoQuoteBoldOrderRepositoryFactory;
        $this->logger = $logger;
        $this->timezoneInterface = $timezoneInterface;
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
        $publicOrderId = $quote->getExtensionAttributes()->getBoldOrderId() ?? $this->checkoutData->getPublicOrderId();

        if ($publicOrderId && $quoteId) {
            $this->saveBoldQuotePublicOrderRelation($publicOrderId, (string) $quoteId);
        }

        $websiteId = (int)$quote->getStore()->getWebsiteId();
        $this->hydrateOrderFromQuote->hydrate($quote, $publicOrderId);
        $transactionData = $this->authorize->execute($publicOrderId, $websiteId);
        $this->saveTransactionData($order, $transactionData);
        $timestamp = $this->timezoneInterface->date()->format('Y-m-d H:i:s');
        $this->saveAuthorizedAt($timestamp, (string) $quoteId);
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
     * Save Bold Public Order ID and Quote ID
     *
     * @param string $publicOrderId
     * @param string $quoteId
     * @return void
     */
    private function saveBoldQuotePublicOrderRelation(string $publicOrderId, string $quoteId): void
    {
        $repository = $this->magentoQuoteBoldOrderRepositoryFactory->create();
        try {
            /** @var MagentoQuoteBoldOrderInterface&MagentoQuoteBoldOrder $relation */
            $relation = $repository->findOrCreateByQuoteId($quoteId);
            $relation->setQuoteId($quoteId);
            $relation->setBoldOrderId($publicOrderId);
            $repository->save($relation);
            return;
        } catch (LocalizedException | Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    /**
     * Save Successful Authorize Full Amount at to Bold Quote Public Order Relation
     *
     * @param string $timestamp
     * @param string $quoteId
     * @return void
     */
    private function saveAuthorizedAt(string $timestamp, string $quoteId): void
    {
        $repository = $this->magentoQuoteBoldOrderRepositoryFactory->create();
        try {
            /** @var MagentoQuoteBoldOrderInterface&MagentoQuoteBoldOrder $relation */
            $relation = $repository->findOrCreateByQuoteId($quoteId);
            $relation->setQuoteId($quoteId);
            $relation->setSuccessfulAuthFullAt($timestamp);
            $repository->save($relation);
            return;
        } catch (LocalizedException | Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
