<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

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
     * @param Session $checkoutSession
     */
    public function __construct(
        Session $checkoutSession,
        IsPaymentBoosterAvailable $isPaymentBoosterAvailable,
        InitOrderFromQuote $initOrderFromQuote
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->isPaymentBoosterAvailable = $isPaymentBoosterAvailable;
        $this->initOrderFromQuote = $initOrderFromQuote;
    }

    /**
     * Initialize Bold simple order and save it to checkout session.
     *
     * @return void
     * @throws LocalizedException
     */
    public function initCheckoutData()
    {
        $this->resetCheckoutData();
        $quote = $this->checkoutSession->getQuote();
        if (!$quote) {
            throw new Exception('Quote is not found');
        }
        if (!$this->isPaymentBoosterAvailable->isAvailable()) {
            return;
        }
        $checkoutData = $this->initOrderFromQuote->init($quote);
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
     * @return string|null
     */
    public function getFastlaneStyles(): ?string
    {
        $checkoutData = $this->checkoutSession->getBoldCheckoutData();
        return $checkoutData['data']['fastlane_styles'] ?? null;
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
     * Get EPS gateway ID from checkout session.
     *
     * @return int|null
     */
    public function getEpsGatewayId(): ?int
    {
        $checkoutData = $this->checkoutSession->getBoldCheckoutData();
        return $checkoutData['data']['flow_settings']['eps_gateway_id'] ?? null;
    }
}
