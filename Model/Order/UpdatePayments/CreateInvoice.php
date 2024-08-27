<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\UpdatePayments;

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
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderRepository = $orderRepository;
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
    }
}
