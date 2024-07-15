<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Model\Http\Client\Request\Validator\ShopIdValidator;
use Bold\CheckoutPaymentBooster\Api\Order\GuestHydrateOrderInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;

class GuestHydrateOrder implements GuestHydrateOrderInterface
{
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var HydrateOrderFromQuote
     */
    private $hydrateOrderFromQuote;

    /**
     * @var ShopIdValidator
     */
    private $shopIdValidator;

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param HydrateOrderFromQuote $hydrateOrderFromQuote
     * @param ShopIdValidator $shopIdValidator
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        QuoteIdMaskFactory      $quoteIdMaskFactory,
        HydrateOrderFromQuote   $hydrateOrderFromQuote,
        ShopIdValidator         $shopIdValidator
    ) {
        $this->cartRepository = $cartRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->hydrateOrderFromQuote = $hydrateOrderFromQuote;
        $this->shopIdValidator = $shopIdValidator;
    }

    /**
     * @inheritDoc
     */
    public function hydrate(string $shopId, string $cartId, string $publicOrderId): void
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quote = $this->cartRepository->getActive($quoteIdMask->getQuoteId());
        $storeId = (int)$quote->getStoreId();
        $this->shopIdValidator->validate($shopId, $storeId);

        $this->hydrateOrderFromQuote->hydrate($quote, $publicOrderId);
    }
}