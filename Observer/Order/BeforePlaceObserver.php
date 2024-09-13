<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Order;

use Bold\CheckoutPaymentBooster\Model\Order\Payment\Authorize;
use Bold\CheckoutPaymentBooster\Model\Order\Payment\CheckPaymentMethod;
use Magento\Checkout\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Payment\Transaction;

/**
 * Authorize Bold payments before placing order.
 */
class BeforePlaceObserver implements ObserverInterface
{
    /**
     * @var Authorize
     */
    private $authorize;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var CheckPaymentMethod
     */
    private $checkPaymentMethod;

    /**
     * @param Authorize $authorize
     * @param Session $checkoutSession
     * @param CheckPaymentMethod $checkPaymentMethod
     */
    public function __construct(
        Authorize $authorize,
        Session $checkoutSession,
        CheckPaymentMethod $checkPaymentMethod
    ) {
        $this->authorize = $authorize;
        $this->checkoutSession = $checkoutSession;
        $this->checkPaymentMethod = $checkPaymentMethod;
    }

    /**
     * Authorize Bold payments before placing order.
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order || !$this->checkPaymentMethod->isBold($order)) {
            return;
        }
        $publicOrderId = $this->checkoutSession->getBoldCheckoutData()['data']['public_order_id'] ?? '';
        $websiteId = (int)$order->getStore()->getWebsiteId();
        $response = $this->authorize->execute($publicOrderId, $websiteId);
        $this->saveTransaction($order, $response['data'] ?? []);
    }

    /**
     * Add Bold transaction data to order payment.
     *
     * @param OrderInterface $order
     * @param array $transactionData
     * @return void
     */
    private function saveTransaction(OrderInterface $order, array $transactionData)
    {
        if (!isset($transactionData['transactions'][0]['transaction_id'])) {
            return;
        }
        $order->getPayment()->setTransactionId($transactionData['transactions'][0]['transaction_id']);
        $order->getPayment()->setIsTransactionClosed(0);
        $order->getPayment()->addTransaction(Transaction::TYPE_AUTH);
        $cardDetails = $transactionData['transactions'][0]['tender_details'] ?? null;
        if (!$cardDetails) {
            return;
        }
        $brand = $cardDetails['brand'] ?? '';
        $lastFour = $cardDetails['last_four'] ?? '';
        if (!$lastFour && isset($cardDetails['line_text'])) {
            preg_match('/\b(\d{4})\b(?=\s*\(Transaction ID)/', $cardDetails['line_text'], $matches);
            $lastFour = $matches[1] ?? '';
        }
        $order->getPayment()->setCcType($brand);
        $order->getPayment()->setCcLast4($lastFour);
        if (!$lastFour && isset($cardDetails['line_text'])) {
            $order->getPayment()->setAdditionalInformation('tender_details', $cardDetails['line_text']);
        }
    }
}
