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
use Magento\Quote\Api\CartRepositoryInterface;

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
     * @var CartRepositoryInterface
     */
    private $cartRepository;

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
     * @param CartRepositoryInterface $cartRepository
     * @param Session $checkoutSession
     * @param CheckPaymentMethod $checkPaymentMethod
     */
    public function __construct(
        Authorize $authorize,
        CartRepositoryInterface $cartRepository,
        Session $checkoutSession,
        CheckPaymentMethod $checkPaymentMethod
    ) {
        $this->authorize = $authorize;
        $this->cartRepository = $cartRepository;
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
        $this->authorize->execute($publicOrderId, $websiteId);
    }
}
