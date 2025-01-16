<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Product;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Checkout\Model\Session;
use Magento\Quote\Api\CartRepositoryInterface;

/**
 * Observer for `controller_action_predispatch_checkout_cart_add` event
 *
 * @see \Magento\Framework\App\FrontController::dispatchPreDispatchEvents
 * @see \Magento\Checkout\Controller\Cart\Add::execute
 */
class ExpressPayBeforeAddToCartObserver implements ObserverInterface
{
    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    public function __construct(Session $checkoutSession, CartRepositoryInterface $cartRepository)
    {
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
    }

    /**
     * Clear the cart before checking out with Express Pay from the product detail page
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $request = $observer->getEvent()->getRequest();
        $isExpressPayOrder = $request->getParam('source') === 'expresspay';

        $quote = $this->checkoutSession->getQuote();

        if (!$isExpressPayOrder || !$quote->hasItems()) {
            return;
        }

        $this->checkoutSession->setCheckoutState(true);
        $quote->removeAllItems();
        $quote->setTotalsCollectedFlag(false);
        $this->checkoutSession->clearQuote();

        $this->cartRepository->save($quote);
    }
}
