<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\ExpressPay\Order;

use Bold\CheckoutPaymentBooster\Api\ExpressPay\Order\CreateInterface;
use Bold\CheckoutPaymentBooster\Api\Http\ClientInterface;
use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Config;
use Bold\CheckoutPaymentBooster\Model\Log\OrderTracker;
use Bold\CheckoutPaymentBooster\Model\Order\SyncPublicOrderIdForQuote;
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

    /**
     * @var Config
     */
    private $config;

    /**
     * @var OrderTracker
     */
    private $orderTracker;

    /**
     * @var SyncPublicOrderIdForQuote
     */
    private $syncPublicOrderIdForQuote;

    /**
     * @var MagentoQuoteBoldOrderRepositoryInterface
     */
    private $magentoQuoteBoldOrderRepository;

    /**
     * @var CheckoutData
     */
    private $checkoutData;

    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        CartRepositoryInterface $cartRepository,
        QuoteConverter $quoteConverter,
        ClientInterface $httpClient,
        SessionManagerInterface $checkoutSession,
        Config $config,
        OrderTracker $orderTracker,
        SyncPublicOrderIdForQuote $syncPublicOrderIdForQuote,
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository,
        CheckoutData $checkoutData
    ) {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->cartRepository = $cartRepository;
        $this->quoteConverter = $quoteConverter;
        $this->httpClient = $httpClient;
        $this->checkoutSession = $checkoutSession;
        $this->config = $config;
        $this->orderTracker = $orderTracker;
        $this->syncPublicOrderIdForQuote = $syncPublicOrderIdForQuote;
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
        $this->checkoutData = $checkoutData;
    }

    public function execute($quoteMaskId, $publicOrderId, $gatewayId, $shippingStrategy, $shouldVault, $paymentSource): array
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
                throw new LocalizedException(
                    __('Could not create Express Pay order. Invalid quote ID "%1".', $quoteId)
                );
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

        if (!$quote->getIsActive()) {
            throw new LocalizedException(
                __('Could not create Express Pay order. The quote is no longer active.')
            );
        }

        if (!count($quote->getAllVisibleItems())) {
            throw new LocalizedException(
                __('Could not create Express Pay order. The cart is empty.')
            );
        }

        $firstName = $quote->getBillingAddress()->getFirstname()
            ?? ($this->config->isUseShippingNameAsFallback((int) $quote->getStore()->getWebsiteId())
                ? $quote->getShippingAddress()->getFirstname()
                : 'noname');

        $hasBillingData = $firstName && $quote->getBillingAddress()->getStreet();

        if (!$hasBillingData && !empty($quote->getShippingAddress()->getShippingMethod())) {
            $quote->getShippingAddress()->setShippingMethod('');
        }

        $websiteId = (int)$quote->getStore()->getWebsiteId();
        $uri = 'checkout/orders/{{shopId}}/wallet_pay';

        $publicOrderIdFromQuote = null;
        $extensionAttributes = $quote->getExtensionAttributes();
        if ($extensionAttributes !== null) {
            $publicOrderIdFromQuote = $extensionAttributes->getBoldOrderId();
        }
        /** @var Session $session */
        $session = $this->checkoutSession;
        $sessionCheckoutData = $session->getBoldCheckoutData();
        $publicOrderIdFromSession = $sessionCheckoutData['data']['public_order_id'] ?? null;

        $this->orderTracker->trace($websiteId, 'express_pay_create', [
            'quote_id' => $quote->getId(),
            'customer_id' => $quote->getCustomerId(),
            'request_public_order_id' => $publicOrderId,
            'quote_ext_public_order_id' => $publicOrderIdFromQuote,
            'session_public_order_id' => $publicOrderIdFromSession,
            'gateway_id' => $gatewayId,
            'grand_total' => $quote->getGrandTotal(),
        ]);

        $publicOrderId = $this->resolvePublicOrderId($publicOrderId, $quote, $websiteId);

        $expressPayData = $this->quoteConverter->convertFullQuote($quote, $gatewayId);
        $expressPayData['shipping_strategy'] = $shippingStrategy;
        $expressPayData['public_order_id'] = $publicOrderId;
        $expressPayData['should_vault'] = $shouldVault;
        $expressPayData['payment_source'] = $paymentSource;

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

        $this->syncPublicOrderIdForQuote->execute($publicOrderId, (string) $quote->getId(), $quote);

        $this->orderTracker->trace($websiteId, 'express_pay_create_success', [
            'quote_id' => $quote->getId(),
            'public_order_id' => $publicOrderId,
            'bold_order_id' => $resultData['data']['order_id'] ?? null,
        ]);

        return [
            'order_id' => $resultData['data']['order_id']
        ];
    }

    /**
     * @param string $requestPublicOrderId
     * @param Quote $quote
     * @param int $websiteId
     * @return string
     * @throws LocalizedException
     */
    private function resolvePublicOrderId(string $requestPublicOrderId, Quote $quote, int $websiteId): string
    {
        if (
            $requestPublicOrderId !== ''
            && $this->magentoQuoteBoldOrderRepository->isPublicOrderCompleted($requestPublicOrderId)
        ) {
            $this->orderTracker->trace($websiteId, 'express_pay_create_completed_public_order_id', [
                'quote_id' => $quote->getId(),
                'request_public_order_id' => $requestPublicOrderId,
            ]);

            /** @var Session $session */
            $session = $this->checkoutSession;
            $session->replaceQuote($quote);
            $this->checkoutData->resetCheckoutData();
            $this->checkoutData->initCheckoutData();

            $freshPublicOrderId = $this->checkoutData->getPublicOrderId();
            if ($freshPublicOrderId === null || $freshPublicOrderId === '') {
                throw new LocalizedException(
                    __('Could not create Express Pay order. Unable to initialize a new Bold order.')
                );
            }

            return $freshPublicOrderId;
        }

        return $requestPublicOrderId;
    }
}
