<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Bold\CheckoutPaymentBooster\Model\Eps\GetFastlaneStyles;
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
     * @param Session $checkoutSession
     * @param IsPaymentBoosterAvailable $isPaymentBoosterAvailable
     * @param InitOrderFromQuote $initOrderFromQuote
     * @param ResumeOrder $resumeOrder
     * @param GetFastlaneStyles $getFastlaneStyles
     */
    public function __construct(
        Session $checkoutSession,
        IsPaymentBoosterAvailable $isPaymentBoosterAvailable,
        InitOrderFromQuote $initOrderFromQuote,
        ResumeOrder $resumeOrder,
        GetFastlaneStyles $getFastlaneStyles
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->isPaymentBoosterAvailable = $isPaymentBoosterAvailable;
        $this->initOrderFromQuote = $initOrderFromQuote;
        $this->resumeOrder = $resumeOrder;
        $this->getFastlaneStyles = $getFastlaneStyles;
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
        if (!$this->isPaymentBoosterAvailable->isAvailable()) {
            return;
        }
        if ($this->getPublicOrderId()) {
            $orderData = $this->resumeOrder->resume(
                $this->getPublicOrderId(),
                (int)$quote->getStore()->getWebsiteId()
            );
            if ($orderData) {
                $checkoutData = $this->checkoutSession->getBoldCheckoutData();
                $checkoutData['data']['jwt_token'] = $orderData['data']['jwt_token'];
                $this->checkoutSession->setBoldCheckoutData($checkoutData);
                return;
            }
        }
        $checkoutData = $this->initOrderFromQuote->init($quote);
        $checkoutData['data']['flow_settings']['fastlane_styles'] = $this->getFastlaneStyles->getStyles(
            (int)$quote->getStore()->getWebsiteId(),
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
     * @return array
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
