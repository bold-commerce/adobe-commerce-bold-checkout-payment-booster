<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Observer\Product;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;

/**
 * Observer for 'checkout_cart_add_product_complete' event
 *
 * @see \Magento\Checkout\Controller\Cart\Add::execute
 */
class ExpressPayAfterAddToCartObserver implements ObserverInterface
{
    /**
     * @var ManagerInterface
     */
    private $messageManager;

    const ADD_TO_CART_SUCCESS_MESSAGE = 'addCartSuccessMessage';

    public function __contruct(ManagerInterface $messageManager)
    {
        $this->messageManager = $messageManager;
    }

    /**
     * Clear the add to cart message after adding from PDP
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $request = $observer->getEvent()->getRequest();
        $isExpressPayOrder = $request->getParam('source') === 'expresspay';

        if (!$isExpressPayOrder) {
            return;
        }

        $this->messageManager->getMessages()->deleteMessageByIdentifier(ExpressPayAfterAddToCartObserver::ADD_TO_CART_SUCCESS_MESSAGE);
    }
}
