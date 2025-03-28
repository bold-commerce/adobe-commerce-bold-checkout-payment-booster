<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order;

use Bold\CheckoutPaymentBooster\Api\Order\GuestHydrateOrderInterface;
use Bold\CheckoutPaymentBooster\Model\Http\Client\Request\Validator\ShopIdValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Hydrate order for guest.
 */
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
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param HydrateOrderFromQuote $hydrateOrderFromQuote
     * @param ShopIdValidator $shopIdValidator
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        HydrateOrderFromQuote $hydrateOrderFromQuote,
        ShopIdValidator $shopIdValidator,
        LoggerInterface $logger
    ) {
        $this->cartRepository = $cartRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->hydrateOrderFromQuote = $hydrateOrderFromQuote;
        $this->shopIdValidator = $shopIdValidator;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function hydrate(string $shopId, string $cartId, string $publicOrderId, AddressInterface $address): void
    {
        try {
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load((int)$cartId, 'masked_id');
            /** @var CartItemInterface&Quote $quote */
            $quote = $this->cartRepository->getActive($quoteIdMask->getQuoteId());
            $storeId = (int)$quote->getStoreId();
            $this->shopIdValidator->validate($shopId, $storeId);

            /** @var AddressInterface&Address $billingAddress */
            $billingAddress = $quote->getBillingAddress();

            $billingAddress->addData($address->getData());

            $this->hydrateOrderFromQuote->hydrate($quote, $publicOrderId);
        } catch (Throwable $e) {
            $this->logger->error(
                'Payment Booster: Not able to hydrate order data for quote with masked ID: '
                . $cartId . ' and public order ID: ' . $publicOrderId . ' Error: ' . $e->getMessage()
            );
            throw new LocalizedException(__('An error occurred during order hydration.'));
        }
    }
}
