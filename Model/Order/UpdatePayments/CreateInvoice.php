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
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
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
     * @param OrderInterface&Order $order
     * @param PaymentInterface[] $payloadPayments
     * @return void
     * @throws AlreadyExistsException
     * @throws LocalizedException
     */
    public function execute(OrderInterface $order, array $payloadPayments): void
    {
        /** Start - Temporary debug code **/
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/bold_checkout_payment_booster.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        /** End - Temporary debug code **/

        $logger->info("========Create Invoice=======");
        $logger->info("Order Id: " . $order->getIncrementId());
        $logger->info("Payments:" . json_encode($payloadPayments));


        if ($order->hasInvoices()) {
            $logger->info("Order already has invoices");
            return;
        }

        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId((int)$order->getId());
        $orderExtensionData->setIsCaptureInProgress(true);
        $this->orderExtensionDataRepository->save($orderExtensionData);
        try {
            /** @var PaymentInterface&Payment $payment */
            $payment = $order->getPayment();
            $invoice = $order->prepareInvoice();
            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $invoice->setEmailSent(1);
            $invoice->setTransactionId($payment->getLastTransId());
            $invoice->getOrder()->setCustomerNoteNotify(1);
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
        $logger->info("========Create Invoice=======");
    }

    /**
     * @param PaymentInterface[] $payloadPayments
     */
    private function addCaptureToOrder(
        OrderInterface $order,
        OrderPaymentInterface $payment,
        array $payloadPayments
    ): void {
        /** Start - Temporary debug code **/
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/bold_checkout_payment_booster.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        /** End - Temporary debug code **/
        $logger->info("========Add capture to Order=======");
        $logger->info("Add capture to Order");
        $logger->info("Order Id: " . $order->getIncrementId());
        $logger->info("Payments:" . json_encode($payment));

        if (count($payloadPayments)) {
            $providerId = $payloadPayments[0]->getTransaction()->getProviderId();
        } else {
            $providerId = '';
        }

        $logger->info("Provider ID: " . $providerId);

        try {
            $capture = $this->transactionBuilder->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($providerId)
                ->setFailSafe(true)
                ->build(Transaction::TYPE_CAPTURE);

            $capture->setIsClosed(1);

            $this->transactionRepository->save($capture);
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
        $logger->info("========Add capture to Order=======");
    }

    /**
     * @param OrderInterface&Order $order
     * @param OrderPaymentInterface&Payment $payment
     */
    private function updateAuthorization(OrderInterface $order, OrderPaymentInterface $payment): void
    {
        /** Beging - Temporary debug code **/
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/bold_checkout_payment_booster.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info("========Update Authorization=======");
        $logger->info("Order Id: " . $order->getIncrementId());
        $logger->info("Payments:" . json_encode($payment));
        /** End - Temporary debug code **/

        try {
            $searchCriteria = $this->searchCriteriaBuilder->addFilter('payment_id', $payment->getId())
                ->addFilter('order_id', $order->getId())
                ->addFilter('txn_type', Transaction::TYPE_AUTH)
                ->create();

            $transactionList = $this->transactionRepository->getList($searchCriteria)->getItems();

            $logger->info("Transaction List: " . json_encode($transactionList));

            /** @var Transaction $auth */
            foreach ($transactionList as $auth) {
                $auth->setIsClosed(1);
                $this->transactionRepository->save($auth);
            }
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
        $logger->info("========Update Authorization=======");
    }
}
