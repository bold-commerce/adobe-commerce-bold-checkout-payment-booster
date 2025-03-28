<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Command;

use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Service;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Payment;

/**
 * Capture order payment on bold side.
 */
class CapturePayment implements CommandInterface
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
     * @param OrderExtensionDataRepository $orderExtensionDataRepository
     */
    public function __construct(
        Service $gatewayService,
        OrderExtensionDataRepository $orderExtensionDataRepository
    ) {
        $this->gatewayService = $gatewayService;
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
    }

    /**
     * {@inheritDoc}
     *
     * @param array{payment: PaymentDataObjectInterface, amount: float} $commandSubject
     * @throws Exception
     */
    public function execute(array $commandSubject): void
    {
        $paymentDataObject = $commandSubject['payment'];
        /** @var InfoInterface&Payment $payment */
        $payment = $paymentDataObject->getPayment();
        $order = $payment->getOrder();
        $amount = (float)$commandSubject['amount'];
        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId((int)$order->getId());
        if (!$orderExtensionData->getPublicId()) {
            throw new LocalizedException(__('Order public id is not set.'));
        }
        if ($orderExtensionData->getIsCaptureInProgress()) {
            return;
        }
        $orderExtensionData->setIsCaptureInProgress(true);
        $this->orderExtensionDataRepository->save($orderExtensionData);
        try {
            if ((float)$order->getGrandTotal() === $amount) {
                $payment->setTransactionId($this->gatewayService->captureFull($order))
                    ->setShouldCloseParentTransaction(true);
                return;
            }
            $payment->setTransactionId($this->gatewayService->capturePartial($order, $amount));
            if ((float)$payment->getBaseAmountAuthorized() === $payment->getBaseAmountPaid() + $amount) {
                $payment->setShouldCloseParentTransaction(true);
            }
        } catch (Exception $e) {
            $orderExtensionData->setIsCaptureInProgress(false);
            $this->orderExtensionDataRepository->save($orderExtensionData);
            throw $e;
        }
        $orderExtensionData->setIsCaptureInProgress(false);
        $this->orderExtensionDataRepository->save($orderExtensionData);
    }
}
