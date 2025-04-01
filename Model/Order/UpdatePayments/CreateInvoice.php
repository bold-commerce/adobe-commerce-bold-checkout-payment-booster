<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments;

use Bold\CheckoutPaymentBooster\Api\Data\Order\Payment\PaymentInterface;
use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Psr\Log\LoggerInterface;

/**
 * Create invoice for order service.
 */
class CreateInvoice
{
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var TransactionRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var Builder
     */
    private $transactionBuilder;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var OrderExtensionDataRepository
     */
    private $orderExtensionDataRepository;

    /**
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        LoggerInterface $logger,
        OrderRepositoryInterface $orderRepository,
        OrderExtensionDataRepository $orderExtensionDataRepository,
        Builder $transactionBuilder,
        TransactionRepositoryInterface $transactionRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
        $this->transactionBuilder = $transactionBuilder;
        $this->transactionRepository = $transactionRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Create order invoice in case payment has been captured on bold checkout.
     *
     * @param OrderInterface $order
     * @param PaymentInterface[] $payloadPayments
     * @return void
     * @throws AlreadyExistsException
     * @throws LocalizedException
     */
    public function execute(OrderInterface $order, array $payloadPayments): void
    {
        if ($order->hasInvoices()) {
            return;
        }
        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId((int)$order->getId());
        $orderExtensionData->setIsCaptureInProgress(true);
        $this->orderExtensionDataRepository->save($orderExtensionData);
        try {
            $payment = $order->getPayment();
            $invoice = $order->prepareInvoice();
            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $invoice->setEmailSent(true);
            $invoice->setTransactionId($payment->getLastTransId());
            $invoice->getOrder()->setCustomerNoteNotify(true);
            $invoice->getOrder()->setIsInProcess(true);
            $order->addRelatedObject($invoice);

            $this->addCaptureToOrder($order, $payment, $payloadPayments);
            $this->updateAuthorization($order, $payment);

            $this->orderRepository->save($order);
            $orderExtensionData->setIsCaptureInProgress(false);
            $this->orderExtensionDataRepository->save($orderExtensionData);
        } catch (LocalizedException $e) {
            $orderExtensionData->setIsCaptureInProgress(false);
            $this->orderExtensionDataRepository->save($orderExtensionData);
            throw $e;
        }
    }

    /**
     * @param PaymentInterface[] $payloadPayments
     */
    private function addCaptureToOrder(
        OrderInterface $order,
        OrderPaymentInterface $payment,
        array $payloadPayments
    ): void {
        if (count($payloadPayments)) {
            $providerId = $payloadPayments[0]->getTransaction()->getProviderId();
        } else {
            $providerId = '';
        }

        try {
            $capture = $this->transactionBuilder->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($providerId)
                ->setFailSafe(true)
                ->build(Transaction::TYPE_CAPTURE);

            $capture->setIsClosed(true);

            $this->transactionRepository->save($capture);
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }

    private function updateAuthorization(OrderInterface $order, OrderPaymentInterface $payment): void
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder->addFilter('payment_id', $payment->getId())
                ->addFilter('order_id', $order->getId())
                ->addFilter('txn_type', Transaction::TYPE_AUTH)
                ->create();

            $transactionList = $this->transactionRepository->getList($searchCriteria)->getItems();

            /** @var Transaction $auth */
            foreach ($transactionList as $auth) {
                $auth->setIsClosed(true);
                $this->transactionRepository->save($auth);
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }
}
