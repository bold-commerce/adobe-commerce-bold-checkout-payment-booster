<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments;

use Bold\CheckoutPaymentBooster\Model\OrderExtensionDataRepository;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;

/**
 * Create invoice for order service.
 */
class CreateInvoice
{
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
        OrderRepositoryInterface $orderRepository,
        OrderExtensionDataRepository $orderExtensionDataRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderExtensionDataRepository = $orderExtensionDataRepository;
    }

    /**
     * Create order invoice in case payment has been captured on bold checkout.
     *
     * @param OrderInterface $order
     * @return void
     * @throws AlreadyExistsException
     * @throws LocalizedException
     */
    public function execute(OrderInterface $order): void
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
            $this->orderRepository->save($order);
            $orderExtensionData->setIsCaptureInProgress(false);
            $this->orderExtensionDataRepository->save($orderExtensionData);
        } catch (LocalizedException $e) {
            $orderExtensionData->setIsCaptureInProgress(false);
            $this->orderExtensionDataRepository->save($orderExtensionData);
            throw $e;
        }
    }
}
