<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Order;

use Bold\CheckoutPaymentBooster\Api\Data\MagentoQuoteBoldOrderInterfaceFactory;
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

    /** @var MagentoQuoteBoldOrderInterfaceFactory */
    private $magentoQuoteBoldOrderFactory;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @param Authorize $authorize
     * @param CartRepositoryInterface $cartRepository
     * @param CheckoutData $checkoutData
     * @param HydrateOrderFromQuote $hydrateOrderFromQuote
     * @param CheckPaymentMethod $checkPaymentMethod
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     * @param MagentoQuoteBoldOrderRepositoryInterfaceFactory $magentoQuoteBoldOrderRepositoryFactory
     * @param MagentoQuoteBoldOrderInterfaceFactory $magentoQuoteBoldOrderFactory
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
        MagentoQuoteBoldOrderInterfaceFactory $magentoQuoteBoldOrderFactory
    ) {
        $this->authorize = $authorize;
        $this->cartRepository = $cartRepository;
        $this->checkoutData = $checkoutData;
        $this->hydrateOrderFromQuote = $hydrateOrderFromQuote;
        $this->checkPaymentMethod = $checkPaymentMethod;
        $this->serializer = $serializer;
        $this->magentoQuoteBoldOrderRepositoryFactory = $magentoQuoteBoldOrderRepositoryFactory;
        $this->magentoQuoteBoldOrderFactory = $magentoQuoteBoldOrderFactory;
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
            /** @var MagentoQuoteBoldOrder $relation */
            $relation = $this->magentoQuoteBoldOrderFactory->create();
            $relation->setQuoteId($quoteId);
            $relation->setBoldOrderId($publicOrderId);
            $repository->save($relation);
            return;
        } catch (LocalizedException | Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}
