<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Product;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
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
     * @var CheckoutData
     */
    protected $checkoutData;    
    
    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    public function __construct(CheckoutData $checkoutData, Session $checkoutSession, CartRepositoryInterface $cartRepository)
    {
        $this->checkoutData = $checkoutData;
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

        // $checkoutData = $this->checkoutSession->getBoldCheckoutData();
        // if($checkoutData === null) {
        //     $this->checkoutData->initCheckoutData();
        // }
    }
}
