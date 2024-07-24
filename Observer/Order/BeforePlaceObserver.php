<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Order;

use Bold\CheckoutPaymentBooster\Model\Order\CheckPaymentMethod;
use Bold\CheckoutPaymentBooster\Model\Order\HydrateOrderFromQuote;
use Bold\CheckoutPaymentBooster\Model\Payment\Authorize;
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
     * @var HydrateOrderFromQuote
     */
    private $hydrateOrderFromQuote;

    /**
     * @var CheckPaymentMethod
     */
    private $checkPaymentMethod;

    /**
     * @param Authorize $authorize
     * @param CartRepositoryInterface $cartRepository
     * @param Session $checkoutSession
     * @param HydrateOrderFromQuote $hydrateOrderFromQuote
     * @param CheckPaymentMethod $checkPaymentMethod
     */
    public function __construct(
        Authorize               $authorize,
        CartRepositoryInterface $cartRepository,
        Session                 $checkoutSession,
        HydrateOrderFromQuote   $hydrateOrderFromQuote,
        CheckPaymentMethod      $checkPaymentMethod
    ) {
        $this->authorize = $authorize;
        $this->cartRepository = $cartRepository;
        $this->checkoutSession = $checkoutSession;
        $this->hydrateOrderFromQuote = $hydrateOrderFromQuote;
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
        $quoteId = $order->getQuoteId();
        $quote = $this->cartRepository->get($quoteId);
        $publicOrderId = $this->checkoutSession->getBoldCheckoutData()['data']['public_order_id'] ?? '';
        $websiteId = (int)$quote->getStore()->getWebsiteId();
        // TODO: check if we need to hydrate order once more.
        $this->hydrateOrderFromQuote->hydrate($quote, $publicOrderId);

        $authorizedPayments = $this->authorize->execute($publicOrderId, $websiteId);
        // TODO: check / process result if needed.
    }
}
