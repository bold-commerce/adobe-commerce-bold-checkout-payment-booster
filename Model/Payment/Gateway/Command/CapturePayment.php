<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Command;

use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionData;
use Bold\CheckoutPaymentBooster\Model\Order\SetIsDelayedCapture;
use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Bold\CheckoutPaymentBooster\Model\Payment\Gateway\Service;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\CommandInterface;

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
     * @var SetIsDelayedCapture
     */
    private $setIsDelayedCapture;

    /**
     * @var OrderExtensionDataRepository
     */
    private $orderExtensionDataRepository;

    /**
     * @param Service $gatewayService
     * @param SetIsDelayedCapture $setIsDelayedCapture
     * @param OrderExtensionDataRepository $orderExtensionDataRepository
     */
    public function __construct(
        Service $gatewayService,
        SetIsDelayedCapture $setIsDelayedCapture,
        OrderExtensionDataRepository $orderExtensionDataRepository
    ) {
        $this->gatewayService = $gatewayService;
        $this->setIsDelayedCapture = $setIsDelayedCapture;
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function execute(array $commandSubject): void
    {
        $paymentDataObject = $commandSubject['payment'];
        $payment = $paymentDataObject->getPayment();
        $order = $payment->getOrder();
        $this->setIsDelayedCapture->set($order);
        $amount = (float)$commandSubject['amount'];
        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId((int)$order->getId());
        if (!$orderExtensionData->getPublicId()) {
            throw new LocalizedException(__('Order public id is not set.'));
        }
        if  ($orderExtensionData->getCaptureAuthority() === OrderExtensionData::AUTHORITY_REMOTE) {
            throw new LocalizedException(__('Payment cannot be captured.'));
        }
        $orderExtensionData->setCaptureAuthority(OrderExtensionData::AUTHORITY_LOCAL);
        $this->orderExtensionDataRepository->save($orderExtensionData);
        if ((float)$order->getGrandTotal() === $amount) {
            $payment->setTransactionId($this->gatewayService->captureFull($order))
                ->setShouldCloseParentTransaction(true);
            return;
        }
        $payment->setTransactionId($this->gatewayService->capturePartial($order, $amount));
        if ((float)$payment->getBaseAmountAuthorized() === $payment->getBaseAmountPaid() + $amount) {
            $payment->setShouldCloseParentTransaction(true);
        }
    }
}
