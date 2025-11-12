<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\Integration\MagentoOrder;

use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\PaymentInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\TransactionInterface;
use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\CancelOrder;
use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\CreateInvoice;
use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\TransactionComment;
use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\TransactionInterface as SalesTransactionInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Magento\Sales\Model\Service\CreditmemoService;
use Psr\Log\LoggerInterface;

/**
 * Service class for managing order payments in integration flow.
 *
 * @api
 */
class Payment
{
    private const FINANCIAL_STATUS_PAID = 'paid';
    private const FINANCIAL_STATUS_REFUNDED = 'refunded';
    private const FINANCIAL_STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
    private const FINANCIAL_STATUS_CANCELLED = 'cancelled';

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var CreateInvoice
     */
    private $createInvoice;

    /**
     * @var CancelOrder
     */
    private $cancelOrder;

    /**
     * @var OrderExtensionDataRepository
     */
    private $orderExtensionDataRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TransactionComment
     */
    private $transactionComment;

    /**
     * @var CreditmemoFactory
     */
    private $creditmemoFactory;

    /**
     * @var CreditmemoService
     */
    private $creditmemoService;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param SerializerInterface $serializer
     * @param CreateInvoice $createInvoice
     * @param CancelOrder $cancelOrder
     * @param OrderExtensionDataRepository $orderExtensionDataRepository
     * @param LoggerInterface $logger
     * @param TransactionComment $transactionComment
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoService $creditmemoService
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository,
        SerializerInterface $serializer,
        CreateInvoice $createInvoice,
        CancelOrder $cancelOrder,
        OrderExtensionDataRepository $orderExtensionDataRepository,
        LoggerInterface $logger,
        TransactionComment $transactionComment,
        CreditmemoFactory $creditmemoFactory,
        CreditmemoService $creditmemoService
    ) {
        $this->orderRepository = $orderRepository;
        $this->serializer = $serializer;
        $this->createInvoice = $createInvoice;
        $this->cancelOrder = $cancelOrder;
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
        $this->logger = $logger;
        $this->transactionComment = $transactionComment;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
    }

    /**
     * Save Bold transaction data to order payment.
     * Extracted from PlaceOrderApi for reuse in integration flow.
     *
     * @param OrderInterface $order Magento order
     * @param array<string, mixed> $transactionData Bold transaction data with transactions array
     * @return void
     * @throws LocalizedException
     */
    public function saveTransactionData(OrderInterface $order, array $transactionData): void
    {
        // Extract transaction data from Bold Checkout authorization response
        $transactions = $transactionData['transactions'] ?? [];
        if (empty($transactions)) {
            return;
        }

        $firstTransaction = $transactions[0];
        $transactionId = $firstTransaction['transaction_id'] ?? null;

        if (!$transactionId) {
            return;
        }

        /** @var OrderPaymentInterface&OrderPayment $orderPayment */
        $orderPayment = $order->getPayment();

        $orderPayment->setTransactionId($transactionId);
        $orderPayment->setIsTransactionClosed(false);
        $orderPayment->addTransaction(SalesTransactionInterface::TYPE_AUTH);

        $tenderDetails = $firstTransaction['tender_details'] ?? null;
        if ($tenderDetails) {
            $orderPayment->setAdditionalInformation(
                'tender_details',
                $this->serializer->serialize($tenderDetails)
            );
        }

        // Save the entire transaction data for reference
        $orderPayment->setAdditionalInformation(
            'bold_transaction_data',
            $this->serializer->serialize($transactionData)
        );

        // Save the order (which includes the payment with all additional information)
        // Note: Transaction history (bold_transactions) is saved during update operations,
        // not during initial order placement
        $this->orderRepository->save($order);
    }

    /**
     * Update order payment based on financial status.
     * Works directly with transactions array format (same as place_order endpoint).
     *
     * @param OrderInterface&Order $order
     * @param string $financialStatus
     * @param array<int, array<string, mixed>> $transactions Transactions array format from Bold Checkout
     * @return void
     * @throws LocalizedException
     * @throws AlreadyExistsException
     */
    public function updatePayment(
        OrderInterface $order,
        string $financialStatus,
        array $transactions
    ): void {
        // Save transaction information to payment additional_information
        $this->saveTransactionInformation($order, $transactions, $financialStatus);

        // Save order to persist transaction information changes
        // (Invoice/CreditMemo/Cancel operations will also save, but we ensure it's saved here)
        $this->orderRepository->save($order);

        // Load order extension data for status checks
        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId((int)$order->getEntityId());

        /** @var OrderInterface&Order $order */
        
        // Process update based on financial status
        switch ($financialStatus) {
            case self::FINANCIAL_STATUS_PAID:
                if (!$orderExtensionData->getIsCaptureInProgress()) {
                    // Convert transactions to PaymentInterface[] format for CreateInvoice
                    $payments = $this->convertTransactionsToPayments($transactions);
                    $this->createInvoice->execute($order, $payments);
                }
                break;
            case self::FINANCIAL_STATUS_REFUNDED:
            case self::FINANCIAL_STATUS_PARTIALLY_REFUNDED:
                if (!$orderExtensionData->getIsRefundInProgress()) {
                    // Calculate refund amount from transactions
                    $refundAmount = $this->calculateRefundAmountFromTransactions($transactions);
                    
                    // Validate refund amount doesn't exceed refundable amount
                    $totalRefundable = $order->getTotalPaid() - $order->getTotalRefunded();
                    if ($refundAmount > $totalRefundable + 0.01) { // Allow 1 cent tolerance for rounding
                        throw new LocalizedException(
                            __(
                                'Refund amount $%1 exceeds remaining refundable amount $%2.',
                                number_format($refundAmount, 2),
                                number_format($totalRefundable, 2)
                            )
                        );
                    }
                    
                    // Create partial credit memo with calculated amount
                    $this->createPartialCreditMemo($order, $refundAmount);
                    
                    // Reload order to get updated totals
                    $order = $this->orderRepository->get((int)$order->getEntityId());
                    
                    // Check if order is now fully refunded and update state accordingly
                    $totalRefunded = $order->getTotalRefunded();
                    $grandTotal = $order->getGrandTotal();
                    if ($totalRefunded >= $grandTotal - 0.01) { // Allow 1 cent tolerance
                        /** @var Order $order */
                        $order->setState(Order::STATE_CLOSED);
                        $order->setStatus(Order::STATE_CLOSED);
                        $this->orderRepository->save($order);
                    }
                }
                break;
            case self::FINANCIAL_STATUS_CANCELLED:
                // Validate order hasn't been captured (no invoices)
                if ($order->hasInvoices()) {
                    throw new LocalizedException(
                        __('Cannot cancel order that has been captured. Please refund the order instead.')
                    );
                }
                if (!$orderExtensionData->getIsCancelInProgress()) {
                    $this->cancelOrder->execute($order);
                    // Reload order to reflect state changes after cancellation
                    $order = $this->orderRepository->get((int)$order->getEntityId());
                }
                break;
            default:
                throw new LocalizedException(__('Unknown financial status.'));
        }
    }

    /**
     * Extract and save payment transaction information from transactions array.
     * Stores provider, provider_id, processed_at, and financial_status in payment additional_information.
     * Maintains all transactions in descending order by processed_at date.
     *
     * @param OrderInterface $order
     * @param array<int, array<string, mixed>> $transactions Transactions array format
     * @param string $financialStatus
     * @return void
     */
    private function saveTransactionInformation(
        OrderInterface $order,
        array $transactions,
        string $financialStatus = 'N/A'
    ): void {
        try {
            $payment = $order->getPayment();
            if (!$payment) {
                $this->logger->warning('Order payment is null, skipping transaction information save', ['order_id' => $order->getEntityId()]);
                return;
            }
            $existingTransactions = $this->transactionComment->getExistingTransactions($order);

            foreach ($transactions as $transactionData) {
                if (!is_array($transactionData)) {
                    continue;
                }

                $provider = $transactionData['gateway'] ?? null;
                $providerId = $transactionData['transaction_id'] ?? $transactionData['payment_id'] ?? null;
                $processedAt = $transactionData['processed_at'] ?? null;

                if ($providerId) {
                    $transactionRecord = [
                        PaymentInterface::PROVIDER => $provider,
                        TransactionInterface::PROVIDER_ID => $providerId,
                        TransactionInterface::PROCESSED_AT => $processedAt,
                        TransactionInterface::STATUS => $financialStatus
                    ];

                    // Check if transaction already exists
                    $exists = false;
                    foreach ($existingTransactions as $existingTx) {
                        if (
                            isset($existingTx[TransactionInterface::PROVIDER_ID])
                            && $existingTx[TransactionInterface::PROVIDER_ID] === $providerId
                        ) {
                            $exists = true;
                            break;
                        }
                    }

                    if (!$exists) {
                        $existingTransactions[] = $transactionRecord;
                    }
                }
            }

            // Sort by processed_at descending (newest first)
            usort($existingTransactions, function ($a, $b) {
                $processedAtA = $a[TransactionInterface::PROCESSED_AT] ?? '';
                $processedAtB = $b[TransactionInterface::PROCESSED_AT] ?? '';
                return $processedAtB <=> $processedAtA;
            });

            $transactionsJson = $this->serializer->serialize(array_reverse($existingTransactions));

            if ($payment instanceof OrderPayment) {
                $payment->setAdditionalInformation('bold_transactions', $transactionsJson);
            }

            // Note: Payment is saved via order save in calling methods
            // Do not save payment separately here to avoid conflicts
        } catch (Exception $exception) {
            $this->logger->critical(
                'Failed to save transaction information: ' . $exception->getMessage(),
                [
                    'exception' => $exception,
                    'order_id' => $order->getEntityId(),
                    'trace' => $exception->getTraceAsString()
                ]
            );
            // Re-throw to surface the error instead of silently failing
            throw $exception;
        }
    }

    /**
     * Convert transactions array format to PaymentInterface[] format for CreateInvoice compatibility.
     *
     * @param array<int, array<string, mixed>> $transactions Transactions array format
     * @return array<int, \Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\PaymentInterface>
     */
    private function convertTransactionsToPayments(array $transactions): array
    {
        $payments = [];
        foreach ($transactions as $transactionData) {
            if (!is_array($transactionData)) {
                continue;
            }

            // Create PaymentInterface object
            $payment = new \Bold\CheckoutPaymentBooster\Model\Order\Payment();
            $payment->setProvider($transactionData['gateway'] ?? '');
            $payment->setPaymentMethod($transactionData['tender_type'] ?? '');
            $payment->setCurrency($transactionData['currency'] ?? '');

            // Create TransactionInterface object
            $transaction = new \Bold\CheckoutPaymentBooster\Model\Order\Transaction();
            $transaction->setProviderId($transactionData['transaction_id'] ?? $transactionData['payment_id'] ?? '');
            $transaction->setProcessedAt($transactionData['processed_at'] ?? '');
            $transaction->setStatus($transactionData['status'] ?? '');

            $payment->setTransaction($transaction);
            $payments[] = $payment;
        }

        return $payments;
    }

    /**
     * Calculate total refund amount from transactions array.
     * Transaction amounts are in cents and need to be converted to dollars.
     *
     * @param array<int, array<string, mixed>> $transactions Transactions array format
     * @return float Total refund amount in dollars
     */
    private function calculateRefundAmountFromTransactions(array $transactions): float
    {
        $totalRefundAmount = 0.0;
        
        foreach ($transactions as $transactionData) {
            if (!is_array($transactionData)) {
                continue;
            }
            
            // Amount is in cents, convert to dollars
            $amount = $transactionData['amount'] ?? 0;
            $totalRefundAmount += ($amount / 100);
        }
        
        return $totalRefundAmount;
    }

    /**
     * Create a partial credit memo for the specified refund amount.
     * Uses progress flags to prevent concurrent operations.
     *
     * @param OrderInterface&Order $order
     * @param float $refundAmount Amount to refund in dollars
     * @return void
     * @throws LocalizedException
     * @throws AlreadyExistsException
     */
    private function createPartialCreditMemo(OrderInterface $order, float $refundAmount): void
    {
        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId((int)$order->getEntityId());
        $orderExtensionData->setIsRefundInProgress(true);
        $this->orderExtensionDataRepository->save($orderExtensionData);
        
        try {
            // Create credit memo from order with empty items (no product refunds)
            // This creates a pure adjustment credit memo
            $creditmemo = $this->creditmemoFactory->createByOrder($order, []);
            
            // Set all item quantities to 0 (we're only doing an adjustment refund)
            foreach ($creditmemo->getAllItems() as $item) {
                $item->setQty(0);
            }
            
            // Set the refund amount via adjustment positive (cast to string as required by Magento)
            $creditmemo->setAdjustmentPositive((string)$refundAmount);
            $creditmemo->setGrandTotal($refundAmount);
            $creditmemo->setBaseGrandTotal($refundAmount);
            
            // Set subtotal to 0 since we're not refunding items
            $creditmemo->setSubtotal(0);
            $creditmemo->setBaseSubtotal(0);
            
            // Refund offline (we're not actually processing payment, just recording it)
            $this->creditmemoService->refund($creditmemo, false);
            
            // Add transaction comment
            $this->transactionComment->addComment('Refunded', $order);
        } catch (LocalizedException $e) {
            $orderExtensionData->setIsRefundInProgress(false);
            $this->orderExtensionDataRepository->save($orderExtensionData);
            throw $e;
        }
        
        $orderExtensionData->setIsRefundInProgress(false);
        $this->orderExtensionDataRepository->save($orderExtensionData);
    }
}

