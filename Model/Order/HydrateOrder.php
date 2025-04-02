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
     * @param Session $checkoutSession
     * @param ShopIdValidator $shopIdValidator
     * @param HydrateOrderFromQuote $hydrateOrderFromQuote
     * @param LoggerInterface $logger
     */
    public function __construct(
        Session $checkoutSession,
        ShopIdValidator $shopIdValidator,
        HydrateOrderFromQuote $hydrateOrderFromQuote,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->shopIdValidator = $shopIdValidator;
        $this->hydrateOrderFromQuote = $hydrateOrderFromQuote;
        $this->logger = $logger;
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
            $this->hydrateOrderFromQuote->hydrate($quote, $publicOrderId);
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
