<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Command;

use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Service;
use Exception;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;

/**
 * Refund bold order payment.
 */
class RefundPayment implements CommandInterface
{
    /**
     * @var Service
     */
    private $gatewayService;

    /**
     * @var OrderExtensionDataRepository
     */
    private $orderExtensionDataRepository;

    /**
     * @var ResourceConnection
     */
    private $connection;

    /**
     * @param Service $gatewayService
     */
    public function __construct(
        Service $gatewayService,
        OrderExtensionDataRepository $orderExtensionDataRepository,
        ResourceConnection $connection
    ) {
        $this->gatewayService = $gatewayService;
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
        $this->connection = $connection;
    }

    /**
     * @inheritDoc
     *
     * @throws Exception
     */
    public function execute(array $commandSubject): void
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = $commandSubject['payment'];
        $amount = (float)$commandSubject['amount'];

        $payment = $paymentDataObject->getPayment();
        $order = $payment->getOrder();
        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId((int)$order->getId());
        if (!$orderExtensionData->getPublicId()) {
            throw new LocalizedException(__('Order public id is not set.'));
        }
        if ($orderExtensionData->getIsRefundInProgress()) {
            return;
        }
        $orderExtensionData->setIsRefundInProgress(true);
        $this->orderExtensionDataRepository->save($orderExtensionData);
        // The save() call above doesn't commit the change because Magento starts a transaction in CreditmemoService
        // before calling this function. We have to manually commit the change to the database so that the value is
        // set before calling Bold Checkout which calls back to the Magento module to update payments.
        $this->connection->getConnection()->commit()->beginTransaction();

        try {
            if ((float)$order->getGrandTotal() <= $amount) {
                $transactionId = $this->gatewayService->refundFull($order);
                $payment->setTransactionId($transactionId)
                    ->setIsTransactionClosed(1)
                    ->setShouldCloseParentTransaction(true);
                return;
            }
            $transactionId = $this->gatewayService->refundPartial($order, $amount);
            $payment->setTransactionId($transactionId)->setIsTransactionClosed(1);
            if ((float)$payment->getBaseAmountPaid() === $payment->getBaseAmountRefunded() + $amount) {
                $payment->setShouldCloseParentTransaction(true);
            }
        } catch (Exception $e) {
            $orderExtensionData->setIsRefundInProgress(false);
            $this->orderExtensionDataRepository->save($orderExtensionData);
            throw $e;
        }
        $orderExtensionData->setIsRefundInProgress(false);
        $this->orderExtensionDataRepository->save($orderExtensionData);
    }
}
