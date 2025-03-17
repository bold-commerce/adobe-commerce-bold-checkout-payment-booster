<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\ExpressPay\Order;

use Bold\CheckoutPaymentBooster\Api\ExpressPay\Order\CreateInterface;
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

class Create implements CreateInterface
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
     * @var SessionManagerInterface
     */
    private $checkoutSession;

    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        CartRepositoryInterface $cartRepository,
        QuoteConverter $quoteConverter,
        ClientInterface $httpClient,
        SessionManagerInterface $checkoutSession
    ) {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->cartRepository = $cartRepository;
        $this->quoteConverter = $quoteConverter;
        $this->httpClient = $httpClient;
        $this->checkoutSession = $checkoutSession;
    }

    public function execute($quoteMaskId, $publicOrderId, $gatewayId, $shippingStrategy): array
    {
        if (!is_numeric($quoteMaskId) && strlen($quoteMaskId) === 32) {
            try {
                $quoteId = $this->maskedQuoteIdToQuoteId->execute($quoteMaskId);
            } catch (NoSuchEntityException $noSuchEntityException) {
                throw new LocalizedException(
                    __('Could not create Express Pay order. Invalid quote mask ID "%1".', $quoteMaskId)
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
                throw new LocalizedException(__('Could not create Express Pay order. Invalid quote ID "%1".', $quoteId));
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

        $hasBillingData = $quote->getBillingAddress()->getFirstname() && $quote->getBillingAddress()->getStreet();

        if (!$hasBillingData && !empty($quote->getShippingAddress()->getShippingMethod())) {
            $quote->getShippingAddress()->setShippingMethod('');
        }

        $websiteId = (int)$quote->getStore()->getWebsiteId();
        $uri = 'checkout/orders/{{shopId}}/wallet_pay';

        $expressPayData = $this->quoteConverter->convertFullQuote($quote, $gatewayId);
        $expressPayData['shipping_strategy'] = $shippingStrategy;
        $expressPayData['public_order_id'] = $publicOrderId;

        try {
            $result = $this->httpClient->post($websiteId, $uri, $expressPayData);
        } catch (Exception $exception) {
            throw new LocalizedException(
                __('Could not create Express Pay order. Error: "%1"', $exception->getMessage())
            );
        }

        $errors = $result->getErrors();

        if (count($errors) > 0) {
            if (is_array($errors[0])) {
                $exceptionMessage = __(
                    'Could not create Express Pay order. Errors: "%1"',
                    implode(', ', array_column($errors, 'message'))
                );
            } else {
                $exceptionMessage = __('Could not create Express Pay order. Error: "%1"', $errors[0]);
            }

            throw new LocalizedException($exceptionMessage);
        }

        /**
         * @var array{
         *     data: array{
         *         order_id: string
         *     }
         * } $resultData
         */
        $resultData = $result->getBody();

        if ($result->getStatus() !== 200 || count($resultData) === 0) {
            throw new LocalizedException(__('An unknown error occurred while creating the Express Pay order.'));
        }

        return [
            'order_id' => $resultData['data']['order_id']
        ];
    }
}
