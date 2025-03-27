<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Command;

use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Service;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Payment;

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
     * @param Service $gatewayService
     */
    public function __construct(
        Service $gatewayService,
        OrderExtensionDataRepository $orderExtensionDataRepository
    ) {
        $this->gatewayService = $gatewayService;
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
    }

    /**
     * @inheritDoc
     *
     * @param array{payment: PaymentDataObjectInterface, amount: float} $commandSubject
     * @throws Exception
     */
    public function execute(array $commandSubject): void
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = $commandSubject['payment'];
        $amount = (float)$commandSubject['amount'];
        /** @var InfoInterface&Payment $payment */
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
