<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Order;

use Bold\Checkout\Api\Data\PlaceOrder\Request\OrderDataInterface;
use Bold\CheckoutPaymentBooster\Model\Order\CheckPaymentMethod;
use Bold\CheckoutPaymentBooster\Model\Order\OrderExtensionDataFactory;
use Bold\CheckoutPaymentBooster\Model\Order\SetCompleteState;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\Order\OrderExtensionData as OrderExtensionDataResource;
use Magento\Checkout\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Authorize Bold payments before placing order.
 */
class AfterSubmitObserver implements ObserverInterface
{
    /**
     * @var Session
     */
    private $session;

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
     * @param CheckPaymentMethod $checkPaymentMethod
     * @param OrderExtensionDataFactory $orderExtensionDataFactory
     * @param OrderExtensionDataResource $orderExtensionDataResource
     */
    public function __construct(
        Session                    $session,
        SetCompleteState           $setCompleteState,
        CheckPaymentMethod         $checkPaymentMethod,
        OrderExtensionDataFactory  $orderExtensionDataFactory,
        OrderExtensionDataResource $orderExtensionDataResource
    ) {
        $this->session = $session;
        $this->orderExtensionDataFactory = $orderExtensionDataFactory;
        $this->orderExtensionDataResource = $orderExtensionDataResource;
        $this->setCompleteState = $setCompleteState;
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
        $orderId = (int)$order->getEntityId();
        $publicOrderId = $this->session->getBoldCheckoutData()['data']['public_order_id'] ?? null;
        $this->session->setBoldCheckoutData(null);
        if (!$publicOrderId) {
            // TODO: remove?
            /** @var OrderDataInterface $orderPayload */
            $orderPayload = $observer->getEvent()->getOrderPayload();
            $publicOrderId = $orderPayload ? $orderPayload->getPublicId() : null;
        }
        if (!$publicOrderId) {
            return;
        }
        $orderExtensionData = $this->orderExtensionDataFactory->create();
        $orderExtensionData->setOrderId($orderId);
        $orderExtensionData->setPublicId($publicOrderId);
        try {
            $this->orderExtensionDataResource->save($orderExtensionData);
        } catch (\Exception $e) {
            return;
        }

        $this->setCompleteState->execute($order);
    }
}
