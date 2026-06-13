<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\Eps\GetFastlaneStyles;
use Bold\CheckoutPaymentBooster\Model\Logger\SessionReuseLogger;
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Bold Checkout service.
 */
class CheckoutData
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var IsPaymentBoosterAvailable
     */
    private $isPaymentBoosterAvailable;

    /**
     * @var InitOrderFromQuote
     */
    private $initOrderFromQuote;

    /**
     * @var ResumeOrder
     */
    private $resumeOrder;

    /**
     * @var GetFastlaneStyles
     */
    private $getFastlaneStyles;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var MagentoQuoteBoldOrderRepositoryInterface
     */
    private $magentoQuoteBoldOrderRepository;

    /**
     * @var SessionReuseLogger
     */
    private $sessionReuseLogger;

    /**
     * @param Session $checkoutSession
     * @param IsPaymentBoosterAvailable $isPaymentBoosterAvailable
     * @param InitOrderFromQuote $initOrderFromQuote
     * @param ResumeOrder $resumeOrder
     * @param GetFastlaneStyles $getFastlaneStyles
     * @param Config $config
     * @param MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository
     * @param SessionReuseLogger $sessionReuseLogger
     */
    public function __construct(
        Session $checkoutSession,
        IsPaymentBoosterAvailable $isPaymentBoosterAvailable,
        InitOrderFromQuote $initOrderFromQuote,
        ResumeOrder $resumeOrder,
        GetFastlaneStyles $getFastlaneStyles,
        Config $config,
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository,
        SessionReuseLogger $sessionReuseLogger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->isPaymentBoosterAvailable = $isPaymentBoosterAvailable;
        $this->initOrderFromQuote = $initOrderFromQuote;
        $this->resumeOrder = $resumeOrder;
        $this->getFastlaneStyles = $getFastlaneStyles;
        $this->config = $config;
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
        $this->sessionReuseLogger = $sessionReuseLogger;
    }

    /**
     * Initialize Bold simple order and save it to checkout session.
     *
     * @return void
     * @throws LocalizedException
     */
    public function initCheckoutData()
    {
        $quote = $this->checkoutSession->getQuote();
        $websiteId = (int)$quote->getStore()->getWebsiteId();
        $quoteId = (string)$quote->getId();

        $this->sessionReuseLogger->info(
            'initCheckoutData: called',
            [
                'quote_id' => $quoteId,
                'website_id' => $websiteId,
            ]
        );

        if (!$this->config->getShopId($websiteId)) {
            throw new LocalizedException(__('Shop ID is not configured for website "%1".', $websiteId));
        }

        if (!$this->isPaymentBoosterAvailable->isAvailable()) {
            $this->sessionReuseLogger->info(
                'initCheckoutData: payment booster not available, skipping',
                ['quote_id' => $quoteId]
            );
            return;
        }

        $existingPublicOrderId = $this->getPublicOrderId();
        $isQuoteProcessed = $existingPublicOrderId
            ? $this->magentoQuoteBoldOrderRepository->isQuoteProcessed($quoteId)
            : false;

        $this->sessionReuseLogger->info(
            'initCheckoutData: evaluating Bold session',
            [
                'quote_id' => $quoteId,
                'public_order_id' => $existingPublicOrderId,
                'is_quote_processed' => $isQuoteProcessed,
            ]
        );

        // Quote already placed via wallet: do not resume its completed Bold order on a new checkout.
        if ($existingPublicOrderId && $isQuoteProcessed) {
            $this->resetCheckoutData('initCheckoutData:processed_quote');
            $existingPublicOrderId = null;

            $this->sessionReuseLogger->info(
                'initCheckoutData: cleared stale Bold session for processed quote; will init new order',
                ['quote_id' => $quoteId]
            );
        }

        // In-progress checkout: resume the existing Bold order (e.g. page reload on payment step).
        if ($existingPublicOrderId) {
            $orderData = $this->resumeOrder->resume(
                $existingPublicOrderId,
                $websiteId
            );
            if ($orderData) {
                $checkoutData = $this->checkoutSession->getBoldCheckoutData();
                $checkoutData['data']['jwt_token'] = $orderData['data']['jwt_token'];
                $this->checkoutSession->setBoldCheckoutData($checkoutData);

                $this->sessionReuseLogger->info(
                    'initCheckoutData: resumed existing Bold order',
                    [
                        'quote_id' => $quoteId,
                        'public_order_id' => $existingPublicOrderId,
                        'is_quote_processed' => $isQuoteProcessed,
                    ]
                );

                return;
            }

            $this->sessionReuseLogger->warning(
                'initCheckoutData: resume returned no data; falling back to initOrderFromQuote',
                [
                    'quote_id' => $quoteId,
                    'public_order_id' => $existingPublicOrderId,
                    'is_quote_processed' => $isQuoteProcessed,
                ]
            );
        }

        $checkoutData = $this->initOrderFromQuote->init($quote);
        $checkoutData['data']['flow_settings']['fastlane_styles'] = $this->getFastlaneStyles->getStyles(
            $websiteId,
            $quote->getStore()->getBaseUrl()
        );
        $this->checkoutSession->setBoldCheckoutData($checkoutData);

        $this->sessionReuseLogger->info(
            'initCheckoutData: initialized new Bold order',
            [
                'quote_id' => $quoteId,
                'public_order_id' => $checkoutData['data']['public_order_id'] ?? null,
                'previous_public_order_id' => $existingPublicOrderId,
            ]
        );
    }

    /**
     * Remove Bold order data from session.
     *
     * @return void
     */
    public function resetCheckoutData(?string $reason = null): void
    {
        $publicOrderId = $this->getPublicOrderId();
        $quoteId = (string)$this->checkoutSession->getQuoteId();

        $this->checkoutSession->setBoldCheckoutData(null);

        $this->sessionReuseLogger->info(
            'resetCheckoutData: cleared Bold checkout session',
            [
                'reason' => $reason ?? 'unspecified',
                'quote_id' => $quoteId,
                'cleared_public_order_id' => $publicOrderId,
            ]
        );
    }

    /**
     * Get Bold public order ID from checkout session.
     *
     * @return string|null
     */
    public function getPublicOrderId(): ?string
    {
        $checkoutData = $this->checkoutSession->getBoldCheckoutData();
        return $checkoutData['data']['public_order_id'] ?? null;
    }

    /**
     * Get quote from checkout session.
     *
     * @return CartInterface
     */
    public function getQuote(): CartInterface
    {
        return $this->checkoutSession->getQuote();
    }

    /**
     * Get Fastlane styles from checkout session.
     *
     * @return array{privacy: "yes"|"no", input: string[], root: string[]}
     */
    public function getFastlaneStyles(): array
    {
        $checkoutData = $this->checkoutSession->getBoldCheckoutData();
        return $checkoutData['data']['flow_settings']['fastlane_styles'] ?? [];
    }

    /**
     * Get JWT token from checkout session.
     *
     * @return string|null
     */
    public function getJwtToken(): ?string
    {
        $checkoutData = $this->checkoutSession->getBoldCheckoutData();
        return $checkoutData['data']['jwt_token'] ?? null;
    }

    /**
     * Get EPS auth token from checkout session.
     *
     * @return string|null
     */
    public function getEpsAuthToken(): ?string
    {
        $checkoutData = $this->checkoutSession->getBoldCheckoutData();
        return $checkoutData['data']['flow_settings']['eps_auth_token'] ?? null;
    }

    /**
     * Get Payment Gateway ID from the flow in the checkout session.
     *
     * @return int|null
     */
    public function getPaymentGatewayId(): ?int
    {
        $checkoutData = $this->checkoutSession->getBoldCheckoutData();
        return $checkoutData['data']['flow_settings']['eps_gateway_id'] ?? null;
    }

    /**
     * Get EPS payment gateways from checkout session.
     *
     * @return array{auth_token: string, currency: string, gateway: string, id: int, is_test_mode: bool}[]
     */
    public function getPaymentGateways(): array
    {
        $checkoutData = $this->checkoutSession->getBoldCheckoutData();
        return $checkoutData['data']['payment_gateways'] ?? [];
    }

    /**
     * Get Checkout should_vault setting (indicating whether vaulting is enabled for the shop) from checkout session.
     *
     * @return bool
     */
    public function getShouldVault(): bool
    {
        $checkoutData = $this->checkoutSession->getBoldCheckoutData();
        return $checkoutData['data']['should_vault'] ?? false;
    }
}
