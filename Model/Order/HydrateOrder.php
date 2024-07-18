<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Api\Order\HydrateOrderInterface;
use Bold\CheckoutPaymentBooster\Model\Http\Client\Request\Validator\ShopIdValidator;
use Magento\Checkout\Model\Session;

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
     * @param Session $checkoutSession
     * @param ShopIdValidator $shopIdValidator
     * @param HydrateOrderFromQuote $hydrateOrderFromQuote
     */
    public function __construct(
        Session $checkoutSession,
        ShopIdValidator $shopIdValidator,
        HydrateOrderFromQuote $hydrateOrderFromQuote
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->shopIdValidator = $shopIdValidator;
        $this->hydrateOrderFromQuote = $hydrateOrderFromQuote;
    }

    /**
     * @inheritDoc
     */
    public function hydrate(string $shopId, string $publicOrderId): void
    {
        $quote = $this->checkoutSession->getQuote();
        $storeId = $quote->getStoreId();
        $this->shopIdValidator->validate($shopId, $storeId);

        $this->hydrateOrderFromQuote->hydrate($quote, $publicOrderId);
    }
}
