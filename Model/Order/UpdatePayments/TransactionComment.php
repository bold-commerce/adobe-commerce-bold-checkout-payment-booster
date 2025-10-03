<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments;

use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\PaymentInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\TransactionInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Magento\Framework\Serialize\Serializer\Json as Serializer;

class TransactionComment
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param Serializer $serializer
     */
    public function __construct(
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        Serializer $serializer
    ) {
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->serializer = $serializer;
    }

    /**
     * Add comment to order
     *
     * @param string $action
     * @param Order $order
     * @return void
     */
    public function addComment(string $action, Order $order)
    {
        try {
            $comment = $this->getTransactionComment($action, $order);
            if (!empty($comment)) {
                $order->addCommentToStatusHistory($this->getTransactionComment($action, $order));
                $this->orderRepository->save($order);
            }
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }
    }

    /**
     * Build a comment string with transaction details from Bold
     *
     * @param string $action
     * @param OrderInterface $order
     * @return string
     */
    private function getTransactionComment(string $action, OrderInterface $order): string
    {
        $payment = $order->getPayment();

        if (!$payment) {
            return '';
        }

        $transactions = $this->getExistingTransactions($order);

        $comment = $action . ' via Bold Payment Booster.';

        if (empty($transactions)) {
            return $comment;
        }

        $latestTransaction = $transactions[0] ?? null;

        if (!is_array($latestTransaction)) {
            return $comment;
        }

        $providerId = $latestTransaction[TransactionInterface::PROVIDER_ID] ?? null;
        if (!empty($providerId)) {
            $comment .= ' Transaction ID: ' . $providerId;
        }

        $provider = $latestTransaction[PaymentInterface::PROVIDER] ?? null;
        if (!empty($provider)) {
            $comment .= ' (Provider: ' . $provider . ')';
        }

        $processedAt = $latestTransaction[TransactionInterface::PROCESSED_AT] ?? null;
        if (!empty($processedAt)) {
            $comment .= ' Processed at: ' . $processedAt;
        }

        return $comment;
    }

    /**
     * Retrieves and sorts the existing transactions associated with the order's payment.
     *
     * @param OrderInterface $order The order object containing the payment information.
     * @return array<int, array<string, string>> The sorted array of existing transactions, or an
     * empty array if no transactions are found.
     */
    public function getExistingTransactions(OrderInterface $order): array
    {
        $payment = $order->getPayment();

        // Use concrete class method to get specific key
        if ($payment instanceof \Magento\Sales\Model\Order\Payment) {
            $transactionsData = $payment->getAdditionalInformation('bold_transactions');
        } else {
            // Fallback: get all additional info and extract the key
            $allInfo = $payment->getAdditionalInformation();
            $transactionsData = is_array($allInfo) ? ($allInfo['bold_transactions'] ?? null) : null;
        }

        // Handle JSON string (new format) or array (legacy format)
        if (is_string($transactionsData)) {
            $decoded = $this->serializer->unserialize($transactionsData);
            $existingTransactions = is_array($decoded) ? $decoded : [];
        } elseif (is_array($transactionsData)) {
            $existingTransactions = $transactionsData;
        } else {
            $existingTransactions = [];
        }

        // Sort by processed_at in descending order (newest first)
        usort($existingTransactions, function ($a, $b) {
            if (!is_array($a) || !is_array($b)) {
                return 0;
            }
            return ($b[TransactionInterface::PROCESSED_AT] ?? '') <=> ($a[TransactionInterface::PROCESSED_AT] ?? '');
        });

        return $existingTransactions;
    }
}
