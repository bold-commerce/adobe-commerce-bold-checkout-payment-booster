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
 * Void bold order.
 */
class VoidPayment implements CommandInterface
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
     * @inheritDoc
     *
     * @param array{payment: PaymentDataObjectInterface} $commandSubject
     * @throws Exception
     */
    public function execute(array $commandSubject): void
    {
        $paymentDataObject = $commandSubject['payment'];
        /** @var InfoInterface&Payment $payment */
        $payment = $paymentDataObject->getPayment();
        $order = $payment->getOrder();
        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId((int)$order->getId());
        if (!$orderExtensionData->getPublicId()) {
            throw new LocalizedException(__('Order public id is not set.'));
        }
        if ($orderExtensionData->getIsCancelInProgress()) {
            return;
        }
        $orderExtensionData->setIsCancelInProgress(true);
        $this->orderExtensionDataRepository->save($orderExtensionData);
        try {
            $this->gatewayService->cancel($order, Service::VOID);
        } catch (Exception $e) {
            $orderExtensionData->setIsCancelInProgress(false);
            $this->orderExtensionDataRepository->save($orderExtensionData);
            throw $e;
        }
        $orderExtensionData->setIsCancelInProgress(false);
        $this->orderExtensionDataRepository->save($orderExtensionData);
    }
}
