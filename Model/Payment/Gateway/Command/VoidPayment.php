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
 * Void bold order.
 */
class VoidPayment implements CommandInterface
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
        $this->setIsDelayedCapture->set($payment->getOrder());
        $order = $payment->getOrder();
        $orderExtensionData = $this->orderExtensionDataRepository->getByOrderId((int)$order->getId());
        if (!$orderExtensionData->getPublicId()) {
            throw new LocalizedException(__('Order public id is not set.'));
        }
        if  ($orderExtensionData->getCancelAuthority() === OrderExtensionData::AUTHORITY_REMOTE) {
            throw new LocalizedException(__('Payment cannot be cancelled.'));
        }
        $orderExtensionData->setCancelAuthority(OrderExtensionData::AUTHORITY_LOCAL);
        $this->orderExtensionDataRepository->save($orderExtensionData);
        $this->gatewayService->cancel($order, Service::VOID);
    }
}
