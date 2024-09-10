<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\ExpressPay\Order;

use Bold\Checkout\Api\Http\ClientInterface;
use Bold\CheckoutPaymentBooster\Service\ExpressPay\QuoteConverter;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;

use function __;
use function array_merge_recursive;
use function count;
use function implode;
use function is_numeric;
use function strlen;

/**
 * @api
 */
class Update
{
    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;
    /**
     * @var QuoteConverter
     */
    private $quoteConverter;
    /**
     * @var ClientInterface
     */
    private $httpClient;

    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        CartRepositoryInterface $cartRepository,
        QuoteConverter $quoteConverter,
        ClientInterface $httpClient
    ) {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->cartRepository = $cartRepository;
        $this->quoteConverter = $quoteConverter;
        $this->httpClient = $httpClient;
    }

    /**
     * @param string|int $quoteMaskId
     * @param string $paypalOrderId
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute($quoteMaskId, $paypalOrderId): void
    {
        if (!is_numeric($quoteMaskId) && strlen($quoteMaskId) === 32) {
            try {
                $quoteId = $this->maskedQuoteIdToQuoteId->execute($quoteMaskId);
            } catch (NoSuchEntityException $noSuchEntityException) {
                throw new LocalizedException(
                    __('Could not update Express Pay order. Invalid quote mask ID "%1".', $quoteMaskId)
                );
            }
        } else {
            $quoteId = $quoteMaskId;
        }

        try {
            /** @var Quote $quote */
            $quote = $this->cartRepository->get((int)$quoteId);
        } catch (NoSuchEntityException $noSuchEntityException) {
            throw new LocalizedException(__('Could not update Express Pay order. Invalid quote ID "%1".', $quoteId));
        }

        $websiteId = (int)$quote->getStore()->getWebsiteId();
        $uri = "/checkout/orders/{{shopId}}/wallet_pay/$paypalOrderId";
        $expressPayData = array_merge_recursive(
            $this->quoteConverter->convertShippingInformation($quote),
            $this->quoteConverter->convertQuoteItems($quote),
            $this->quoteConverter->convertTotal($quote),
            $this->quoteConverter->convertTaxes($quote),
            $this->quoteConverter->convertDiscount($quote)
        );

        try {
            $result = $this->httpClient->patch($websiteId, $uri, $expressPayData);
        } catch (Exception $exception) {
            throw new LocalizedException(
                __('Could not update Express Pay order. Error: "%1"', $exception->getMessage())
            );
        }

        $errors = $result->getErrors();

        if (count($errors) > 0) {
            throw new LocalizedException(
                __('Could not update Express Pay order. Errors: "%1"', implode(', ', $errors))
            );
        }

        if ($result->getStatus() !== 204) {
            throw new LocalizedException(__('An unknown error occurred while updating the Express Pay order.'));
        }
    }
}
