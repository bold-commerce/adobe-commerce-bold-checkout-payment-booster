<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\ExpressPay\Order;

use Bold\CheckoutPaymentBooster\Api\ExpressPay\Order\GetInterface as GetExpressPayOrder;
use Bold\CheckoutPaymentBooster\Api\ExpressPay\Order\UpdateInterface;
use Bold\CheckoutPaymentBooster\Api\Http\ClientInterface;
use Bold\CheckoutPaymentBooster\Service\ExpressPay\QuoteConverter;
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;

use function __;
use function array_column;
use function count;
use function implode;
use function is_array;
use function is_numeric;
use function strlen;

class Update implements UpdateInterface
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

    /**
     * @var GetExpressPayOrder
     */
    private $getExpressPayOrder;

    /**
     * @var SessionManagerInterface
     */
    private $checkoutSession;

    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        CartRepositoryInterface $cartRepository,
        QuoteConverter $quoteConverter,
        GetExpressPayOrder $getExpressPayOrder,
        ClientInterface $httpClient,
        SessionManagerInterface $checkoutSession
    ) {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->cartRepository = $cartRepository;
        $this->quoteConverter = $quoteConverter;
        $this->getExpressPayOrder = $getExpressPayOrder;
        $this->httpClient = $httpClient;
        $this->checkoutSession = $checkoutSession;
    }

    public function execute($quoteMaskId, $gatewayId, $paypalOrderId): void
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

        if ($quoteId !== '') {
            try {
                /** @var Quote $quote */
                $quote = $this->cartRepository->get((int)$quoteId);
            } catch (NoSuchEntityException $noSuchEntityException) {
                throw new LocalizedException(__('Could not update Express Pay order. Invalid quote ID "%1".', $quoteId));
            }
        } else {
            try {
                /** @var Session $session */
                $session = $this->checkoutSession;
                /** @var Quote $quote */
                $quote = $session->getQuote();
            } catch (NoSuchEntityException $noSuchEntityException) {
                throw new LocalizedException(__('Active quote not found.'));
            }
        }

        $websiteId = (int)$quote->getStore()->getWebsiteId();
        $uri = "checkout/orders/{{shopId}}/wallet_pay/$paypalOrderId";
        $expressPayData = $this->quoteConverter->convertFullQuote($quote, $gatewayId);

        try {
            $expressPayOrder = $this->getExpressPayOrder->execute($paypalOrderId, $gatewayId);
        } catch (LocalizedException $localizedException) {
            $expressPayOrder = null;
        }

        if ($expressPayOrder !== null) {
            $expressPayOrderShipping = $expressPayOrder->getShippingAddress();
            $hasShippingData = !empty($expressPayOrderShipping->getCountry())
                && !empty($expressPayOrderShipping->getCity());

            if (!$hasShippingData) {
                unset($expressPayData['order_data']['shipping_address']);
            }
        }

        try {
            $result = $this->httpClient->patch($websiteId, $uri, $expressPayData);
        } catch (Exception $exception) {
            throw new LocalizedException(
                __('Could not update Express Pay order. Error: "%1"', $exception->getMessage())
            );
        }

        $errors = $result->getErrors();

        if (count($errors) > 0) {
            if (is_array($errors[0])) {
                $exceptionMessage = __(
                    'Could not update Express Pay order. Errors: "%1"',
                    implode(', ', array_column($errors, 'message'))
                );
            } else {
                $exceptionMessage = __('Could not update Express Pay order. Error: "%1"', $errors[0]);
            }

            throw new LocalizedException($exceptionMessage);
        }

        if ($result->getStatus() !== 204) {
            throw new LocalizedException(__('An unknown error occurred while updating the Express Pay order.'));
        }
    }
}
