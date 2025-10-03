<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\PaymentInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\ResultInterface;
use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\ResultInterfaceFactory;
use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\TransactionInterface;
use Bold\CheckoutPaymentBooster\Api\Order\UpdatePaymentsInterface;
use Bold\CheckoutPaymentBooster\Model\Http\SharedSecretAuthorization;
use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\CancelOrder;
use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\CreateCreditMemo;
use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\CreateInvoice;
use Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments\TransactionComment;
use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\GetWebsiteIdByShopId;
use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Magento\Framework\Serialize\Serializer\Json as Serializer;

class UpdatePayments implements UpdatePaymentsInterface
{
    private const FINANCIAL_STATUS_PAID = 'paid';
    private const FINANCIAL_STATUS_REFUNDED = 'refunded';
    private const FINANCIAL_STATUS_PARTIALLY_REFUNDED = 'partially_refunded';
    private const FINANCIAL_STATUS_CANCELLED = 'cancelled';

    /**
     * @var SharedSecretAuthorization
     */
    private $sharedSecretAuthorization;

    /**
     * @var GetWebsiteIdByShopId
     */
    private $getWebsiteIdByShopId;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var CreateInvoice
     */
    private $createInvoice;

    /**
     * @var CreateCreditMemo
     */
    private $createCreditMemo;

    /**
     * @var CancelOrder
     */
    private $cancelOrder;

    /**
     * @var ResultInterfaceFactory
     */
    private $responseFactory;

    /**
     * @var OrderExtensionDataRepository
     */
    private $orderExtensionDataRepository;

    /** @var OrderPaymentRepositoryInterface  */
    private $paymentRepository;

    /** @var LoggerInterface */
    private $logger;

    /** @var TransactionComment  */
    private $transactionComment;

    /** @var Serializer */
    private $serializer;

    /**
     * Constructor
     *
     * @param ResultInterfaceFactory $responseFactory
     * @param SharedSecretAuthorization $sharedSecretAuthorization
     * @param GetWebsiteIdByShopId $getWebsiteIdByShopId
     * @param OrderRepositoryInterface $orderRepository
     * @param CreateInvoice $createInvoice
     * @param CreateCreditMemo $createCreditMemo
     * @param CancelOrder $cancelOrder
     * @param OrderExtensionDataRepository $orderExtensionDataRepository
     * @param OrderPaymentRepositoryInterface $paymentRepository
     * @param LoggerInterface $logger
     * @param TransactionComment $transactionComment
     * @param Serializer $serializer
     */
    public function __construct(
        ResultInterfaceFactory $responseFactory,
        SharedSecretAuthorization $sharedSecretAuthorization,
        GetWebsiteIdByShopId $getWebsiteIdByShopId,
        OrderRepositoryInterface $orderRepository,
        CreateInvoice $createInvoice,
        CreateCreditMemo $createCreditMemo,
        CancelOrder $cancelOrder,
        OrderExtensionDataRepository $orderExtensionDataRepository,
        OrderPaymentRepositoryInterface $paymentRepository,
        LoggerInterface $logger,
        TransactionComment $transactionComment,
        Serializer $serializer
    ) {
        $this->responseFactory = $responseFactory;
        $this->sharedSecretAuthorization = $sharedSecretAuthorization;
        $this->getWebsiteIdByShopId = $getWebsiteIdByShopId;
        $this->orderRepository = $orderRepository;
        $this->createInvoice = $createInvoice;
        $this->createCreditMemo = $createCreditMemo;
        $this->cancelOrder = $cancelOrder;
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
        $this->paymentRepository = $paymentRepository;
        $this->logger = $logger;
        $this->transactionComment = $transactionComment;
        $this->serializer = $serializer;
    }

    /**
     * Update payment
     *
     * @param string $shopId
     * @param string $financialStatus
     * @param int $platformOrderId
     * @param array<int, mixed> $payments
     * @return ResultInterface
     * @throws AlreadyExistsException
     * @throws AuthorizationException
     * @throws LocalizedException
     */
    public function update(
        string $shopId,
        string $financialStatus,
        int $platformOrderId,
        array $payments
    ): ResultInterface {
        $websiteId = $this->getWebsiteIdByShopId->getWebsiteId($shopId);
        // Do not remove this check until resource authorized by ACL.
        if (!$this->sharedSecretAuthorization->isAuthorized($websiteId)) {
            // Shared secret authorization failed.
            throw new AuthorizationException(__('The consumer isn\'t authorized to access resource.'));
        }
        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId($platformOrderId);
        if (!$orderExtensionData->getPublicId()) {
            throw new LocalizedException(__('Public Order ID does not match.'));
        }
        /** @var OrderInterface&Order $order */
        $order = $this->orderRepository->get($platformOrderId);
        $this->processUpdate($order, $orderExtensionData, $financialStatus, $payments);

        return $this->responseFactory->create(
            [
                'platformId' => $order->getId(),
                'platformFriendlyId' => $order->getIncrementId(),
                'platformCustomerId' => $order->getCustomerId() ?: null,
            ]
        );
    }

    /**
     * Process update based on financial status.
     *
     * @param OrderInterface&Order $order
     * @param OrderExtensionData $orderExtensionData
     * @param string $financialStatus
     * @param PaymentInterface[] $payments
     * @return void
     * @throws LocalizedException
     * @throws AlreadyExistsException
     */
    private function processUpdate(
        OrderInterface $order,
        OrderExtensionData $orderExtensionData,
        string $financialStatus,
        array $payments
    ): void {

        $this->saveTransactionInAdditionalInformation($order, $payments, $financialStatus);

        switch ($financialStatus) {
            case self::FINANCIAL_STATUS_PAID:
                if (!$orderExtensionData->getIsCaptureInProgress()) {
                    $this->createInvoice->execute($order, $payments);
                }
                break;
            case self::FINANCIAL_STATUS_REFUNDED:
            case self::FINANCIAL_STATUS_PARTIALLY_REFUNDED:
                if (!$orderExtensionData->getIsRefundInProgress()) {
                    $this->createCreditMemo->execute($order);
                }
                break;
            case self::FINANCIAL_STATUS_CANCELLED:
                if (!$orderExtensionData->getIsCancelInProgress()) {
                    $this->cancelOrder->execute($order);
                }
                break;
            default:
                throw new LocalizedException(__('Unknown financial status.'));
        }
    }

    /**
     * Extract and save payment transaction information from Bold webhook data.
     * Stores provider, provider_id, processed_at, and financial_status in payment additional_information.
     * Maintains all transactions in descending order by processed_at date.
     *
     * @param OrderInterface $order
     * @param PaymentInterface[] $payments
     * @param string $financialStatus
     * @return void
     */
    private function saveTransactionInAdditionalInformation(
        OrderInterface $order,
        array $payments,
        string $financialStatus = 'N/A'
    ): void {
        try {
            $payment = $order->getPayment();
            $existingTransactions = [];
            foreach ($payments as $paymentData) {
                if (!$paymentData instanceof Payment) {
                    continue;
                }
                $provider = $paymentData->getProvider();
                $transaction = $paymentData->getTransaction();

                $processedAt = $transaction->getProcessedAt();
                $providerId = $transaction->getProviderId();

                if ($providerId) {
                    $transactionRecord = [
                        PaymentInterface::PROVIDER => $provider,
                        TransactionInterface::PROVIDER_ID => $providerId,
                        TransactionInterface::PROCESSED_AT => $processedAt,
                        TransactionInterface::STATUS => $financialStatus
                    ];
                    $existingTransactions = $this->addTransactionInformation($order, $transactionRecord);
                }
            }

            $transactionsJson = $this->serializer->serialize(array_reverse($existingTransactions));

            if ($payment instanceof \Magento\Sales\Model\Order\Payment) {
                $payment->setAdditionalInformation('bold_transactions', $transactionsJson);
            }

            $this->paymentRepository->save($payment);
        } catch (Exception $exception) {
            $this->logger->critical($exception);
        }
    }

    /**
     * Adds transaction information to the payment associated with the order.
     *
     * If a transaction with the same provider ID already exists, it will not be added again.
     *
     * @param OrderInterface $order The order whose payment will be updated with transaction information.
     * @param array<string, string> $transactionRecord The transaction record to be added, containing details about
     * the transaction.
     * @return array<int, array<string, string>>
     */
    private function addTransactionInformation(
        OrderInterface $order,
        array $transactionRecord
    ): array {
        $existingTransactions = $this->transactionComment->getExistingTransactions($order);
        $exists = false;
        foreach ($existingTransactions as $existingTx) {
            if (
                isset($existingTx[TransactionInterface::PROVIDER_ID])
                && $existingTx[TransactionInterface::PROVIDER_ID] ===
                $transactionRecord[TransactionInterface::PROVIDER_ID]
            ) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $existingTransactions[] = $transactionRecord;
        }
        return $existingTransactions;
    }
}
