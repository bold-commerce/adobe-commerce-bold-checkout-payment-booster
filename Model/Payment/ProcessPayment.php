<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Payment;

use Bold\CheckoutPaymentBooster\Model\Order\ProcessOrderPayment;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\Data\TransactionInterface;

class ProcessPayment
{
    /**
     * @var \Magento\Sales\Api\Data\OrderPaymentInterfaceFactory
     */
    private $paymentFactory;

    /**
     * @var \Magento\Sales\Api\Data\TransactionInterfaceFactory
     */
    private $transactionFactory;

    /**
     * @var ProcessOrderPayment
     */
    private $processOrderPayment;

    public function __construct(
        \Magento\Sales\Api\Data\OrderPaymentInterfaceFactory $paymentFactory,
        \Magento\Sales\Api\Data\TransactionInterfaceFactory $transactionFactory,
        ProcessOrderPayment $processOrderPayment
    )
    {
        $this->paymentFactory = $paymentFactory;
        $this->transactionFactory = $transactionFactory;
        $this->processOrderPayment = $processOrderPayment;
    }

    public function process(OrderInterface $order, array $data)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $order->getPayment();
        $payment->setTransactionId($data['data']['transactions'][0]['transaction_id']);
        $payment->setIsTransactionClosed(false);
        $payment->addTransaction(TransactionInterface::TYPE_AUTH);
//        $payment->addTransaction(TransactionInterface::TYPE_CAPTURE);
        return;

        /** @var OrderPaymentInterface $payment */
        $payment = $this->paymentFactory->create();
        $transactionData = $data['data']['transactions'][0];
        $cardExpirationMonth = $cardExpirationYear = '';
        if (!empty($transactionData['tender_details']['expiration'])) {
            [$cardExpirationMonth, $cardExpirationYear] = explode(
                '/',
                $transactionData['tender_details']['expiration'],
                2
            );
        }
        $payment->setBaseAmountPaid($transactionData['amount'] / 100);
        $payment->setAmountPaid($transactionData['amount'] / 100);
        $payment->setCcLast4($transactionData['tender_details']['last_four'] ?? '');
        $payment->setCcType($transactionData['tender_details']['brand']);
        $payment->setCcExpMonth($cardExpirationMonth);
        $payment->setCcExpYear($cardExpirationYear);
        // TODO: it's extension attribute
        $payment->setAdditionalInformation(
            [
                'transaction_gateway' => $transactionData['gateway'],
                'transaction_payment_id' => $transactionData['payment_id']
            ]
        );
        $payment->setIsTransactionClosed(true); // TODO: change to false?

        $transaction = $this->transactionFactory->create();
        $transaction->setTxnId($transactionData['transaction_id']);
        $transaction->setTxnType(TransactionInterface::TYPE_PAYMENT);


        $this->processOrderPayment->process($order, $payment, $transaction);
    }
}
