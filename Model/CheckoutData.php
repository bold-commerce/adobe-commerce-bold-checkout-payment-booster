<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Bold\CheckoutPaymentBooster\Api\MagentoQuoteBoldOrderRepositoryInterface;
use Bold\CheckoutPaymentBooster\Model\Eps\GetFastlaneStyles;
use Bold\CheckoutPaymentBooster\Model\Log\CheckoutOrderTracer;
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
     * @var CheckoutOrderTracer
     */
    private $checkoutOrderTracer;

    /**
     * @param Session $checkoutSession
     * @param IsPaymentBoosterAvailable $isPaymentBoosterAvailable
     * @param InitOrderFromQuote $initOrderFromQuote
     * @param ResumeOrder $resumeOrder
     * @param GetFastlaneStyles $getFastlaneStyles
     * @param Config $config
     * @param MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository
     * @param CheckoutOrderTracer $checkoutOrderTracer
     */
    public function __construct(
        Session $checkoutSession,
        IsPaymentBoosterAvailable $isPaymentBoosterAvailable,
        InitOrderFromQuote $initOrderFromQuote,
        ResumeOrder $resumeOrder,
        GetFastlaneStyles $getFastlaneStyles,
        Config $config,
        MagentoQuoteBoldOrderRepositoryInterface $magentoQuoteBoldOrderRepository,
        CheckoutOrderTracer $checkoutOrderTracer
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->isPaymentBoosterAvailable = $isPaymentBoosterAvailable;
        $this->initOrderFromQuote = $initOrderFromQuote;
        $this->resumeOrder = $resumeOrder;
        $this->getFastlaneStyles = $getFastlaneStyles;
        $this->config = $config;
        $this->magentoQuoteBoldOrderRepository = $magentoQuoteBoldOrderRepository;
        $this->checkoutOrderTracer = $checkoutOrderTracer;
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

        if (!$this->config->getShopId($websiteId)) {
            throw new LocalizedException(__('Shop ID is not configured for website "%1".', $websiteId));
        }

        if (!$this->isPaymentBoosterAvailable->isAvailable()) {
            return;
        }

        $existingPublicOrderId = $this->getPublicOrderId();
        $quoteId = (string)$quote->getId();

        $this->checkoutOrderTracer->trace('init_checkout_data_start', [
            'quote_id' => $quoteId,
            'customer_id' => $quote->getCustomerId(),
            'session_public_order_id' => $existingPublicOrderId,
            'quote_processed' => $this->magentoQuoteBoldOrderRepository->isQuoteProcessed($quoteId),
        ]);

        if ($existingPublicOrderId) {
            if ($this->magentoQuoteBoldOrderRepository->isQuoteProcessed($quoteId)) {
                $this->checkoutOrderTracer->trace('init_checkout_data_reset', [
                    'quote_id' => $quoteId,
                    'stale_public_order_id' => $existingPublicOrderId,
                    'reason' => 'quote_already_processed',
                ]);
                $this->resetCheckoutData();
                $existingPublicOrderId = null;
            }
        }

        if ($existingPublicOrderId) {
            $orderData = $this->resumeOrder->resume(
                $existingPublicOrderId,
                $websiteId
            );
            if ($orderData) {
                $this->checkoutOrderTracer->trace('init_checkout_data_resumed', [
                    'quote_id' => $quoteId,
                    'public_order_id' => $existingPublicOrderId,
                ]);
                $checkoutData = $this->checkoutSession->getBoldCheckoutData();
                $checkoutData['data']['jwt_token'] = $orderData['data']['jwt_token'];
                $this->checkoutSession->setBoldCheckoutData($checkoutData);
                return;
            }
            $this->checkoutOrderTracer->trace('init_checkout_data_resume_failed', [
                'quote_id' => $quoteId,
                'public_order_id' => $existingPublicOrderId,
            ]);
        }
        $checkoutData = $this->initOrderFromQuote->init($quote);
        $this->checkoutOrderTracer->trace('init_checkout_data_new_order', [
            'quote_id' => $quoteId,
            'public_order_id' => $checkoutData['data']['public_order_id'] ?? null,
            'previous_session_public_order_id' => $existingPublicOrderId,
        ]);
        $checkoutData['data']['flow_settings']['fastlane_styles'] = $this->getFastlaneStyles->getStyles(
            $websiteId,
            $quote->getStore()->getBaseUrl()
        );
        $this->checkoutSession->setBoldCheckoutData($checkoutData);
    }

    /**
     * Remove Bold order data from session.
     *
     * @return void
     */
    public function resetCheckoutData()
    {
        $this->checkoutSession->setBoldCheckoutData(null);
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
