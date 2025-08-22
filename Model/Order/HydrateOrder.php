<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Api\Order\HydrateOrderInterface;
use Bold\CheckoutPaymentBooster\Model\Http\Client\Request\Validator\ShopIdValidator;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\AddressInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Magento\Framework\Event\ManagerInterface;

/**
 * Hydrate order for the registered customer.
 */
class HydrateOrder implements HydrateOrderInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var ShopIdValidator
     */
    private $shopIdValidator;

    /**
     * @var HydrateOrderFromQuote
     */
    private $hydrateOrderFromQuote;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ManagerInterface
     */
    private $eventManager;

    /**
     * @param Session $checkoutSession
     * @param ShopIdValidator $shopIdValidator
     * @param HydrateOrderFromQuote $hydrateOrderFromQuote
     * @param LoggerInterface $logger
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        Session $checkoutSession,
        ShopIdValidator $shopIdValidator,
        HydrateOrderFromQuote $hydrateOrderFromQuote,
        LoggerInterface $logger,
        ManagerInterface $eventManager
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->shopIdValidator = $shopIdValidator;
        $this->hydrateOrderFromQuote = $hydrateOrderFromQuote;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
    }

    /**
     * @inheritDoc
     */
    public function hydrate(string $shopId, string $publicOrderId, AddressInterface $address): void
    {
        $quote = null;

        try {
            $quote = $this->checkoutSession->getQuote();
            $storeId = $quote->getStoreId();
            $this->shopIdValidator->validate($shopId, $storeId);
            $quote->getBillingAddress()->addData($address->getData());
            $this->eventManager->dispatch(
                'bold_order_hydrate_before',
                ['quote' => $quote, 'publicOrderId' => $publicOrderId]
            );

            $this->hydrateOrderFromQuote->hydrate($quote, $publicOrderId);

            $this->eventManager->dispatch(
                'bold_order_hydrate_after',
                ['quote' => $quote, 'publicOrderId' => $publicOrderId]
            );


        } catch (Throwable $e) {
            $quoteId = $quote ? $quote->getId() : 'N/A';
            $this->logger->error(
                'Payment Booster: Not able to hydrate order data for quote with ID: '
                . $quoteId . ' and public order ID: ' . $publicOrderId . ' Error: ' . $e->getMessage()
            );
            throw new LocalizedException(__('An error occurred during order hydration.'));
        }
    }
}
