<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Order;

use Bold\Checkout\Model\Order\OrderExtensionDataFactory;
use Bold\Checkout\Model\ResourceModel\Order\OrderExtensionData as OrderExtensionDataResource;
use Bold\CheckoutPaymentBooster\Model\Order\Payment\CheckPaymentMethod;
use Bold\CheckoutPaymentBooster\Model\Order\SetCompleteState;
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Update order state to complete on Bold side observer.
 */
class AfterSubmitObserver implements ObserverInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var SetCompleteState
     */
    private $setCompleteState;

    /**
     * @var CheckPaymentMethod
     */
    private $checkPaymentMethod;

    /**
     * @var OrderExtensionDataFactory
     */
    private $orderExtensionDataFactory;

    /**
     * @var OrderExtensionDataResource
     */
    private $orderExtensionDataResource;

    /**
     * @param Session $session
     * @param SetCompleteState $setCompleteState
     * @param OrderExtensionDataFactory $orderExtensionDataFactory
     * @param OrderExtensionDataResource $orderExtensionDataResource
     */
    public function __construct(
        Session $session,
        SetCompleteState $setCompleteState,
        CheckPaymentMethod $checkPaymentMethod,
        OrderExtensionDataFactory $orderExtensionDataFactory,
        OrderExtensionDataResource $orderExtensionDataResource
    ) {
        $this->checkoutSession = $session;
        $this->orderExtensionDataFactory = $orderExtensionDataFactory;
        $this->orderExtensionDataResource = $orderExtensionDataResource;
        $this->setCompleteState = $setCompleteState;
        $this->checkPaymentMethod = $checkPaymentMethod;
    }

    /**
     * Set Bold order status to complete after Magento order has been placed.
     *
     * @param Observer $observer
     * @return void
     * @throws Exception
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order || !$this->checkPaymentMethod->isBold($order)) {
            return;
        }
        $orderId = (int)$order->getEntityId();
        $publicOrderId = $this->checkoutSession->getBoldCheckoutData()['data']['public_order_id'] ?? null;
        $this->checkoutSession->setBoldCheckoutData(null);
        if (!$publicOrderId) {
            return;
        }
        $orderExtensionData = $this->orderExtensionDataFactory->create();
        $orderExtensionData->setOrderId($orderId);
        $orderExtensionData->setPublicId($publicOrderId);
        $this->orderExtensionDataResource->save($orderExtensionData);
        $this->setCompleteState->execute($order, $publicOrderId);
    }
}
