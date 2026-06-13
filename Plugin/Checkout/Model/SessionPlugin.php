<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Plugin\Checkout\Model;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Bold\CheckoutPaymentBooster\Model\Logger\SessionReuseLogger;
use Closure;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * Clears stale Bold checkout session data when Magento drops a digital-wallet quote.
 *
 * After express PayPal / Google Pay completes, Magento calls clearQuote() on the
 * checkout session. For digital-wallet quotes we must reset Bold checkout data at
 * that point; otherwise the next checkout would resume the completed public_order_id
 * instead of creating a new Bold order (session-reuse bug).
 */
class SessionPlugin
{
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * @var CheckoutData
     */
    private $checkoutData;

    /**
     * @var SessionReuseLogger
     */
    private $sessionReuseLogger;

    public function __construct(
        CartRepositoryInterface $quoteRepository,
        CheckoutData $checkoutData,
        SessionReuseLogger $sessionReuseLogger
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->checkoutData = $checkoutData;
        $this->sessionReuseLogger = $sessionReuseLogger;
    }

    /**
     * Intercept quote clearing so completed wallet checkouts do not leave a resumable Bold session.
     *
     * @param Session $subject
     * @param Closure $proceed
     * @return Session
     */
    public function aroundClearQuote(Session $subject, Closure $proceed): Session
    {
        $lastQuoteId = $subject->getLastQuoteId();
        $currentQuoteId = $subject->getQuoteId();
        $publicOrderId = $this->checkoutData->getPublicOrderId();

        $this->sessionReuseLogger->info(
            'clearQuote: intercepted',
            [
                'last_quote_id' => $lastQuoteId,
                'current_quote_id' => $currentQuoteId,
                'public_order_id' => $publicOrderId,
            ]
        );

        if ($lastQuoteId === null) {
            $this->sessionReuseLogger->info(
                'clearQuote: no last_quote_id; proceeding with default Magento clearQuote()',
                ['current_quote_id' => $currentQuoteId]
            );

            return $proceed();
        }

        try {
            /** @var CartInterface&Quote $lastQuote */
            $lastQuote = $this->quoteRepository->get($lastQuoteId);
        } catch (NoSuchEntityException $e) {
            $this->sessionReuseLogger->info(
                'clearQuote: last quote not found; proceeding with default Magento clearQuote()',
                [
                    'last_quote_id' => $lastQuoteId,
                    'current_quote_id' => $currentQuoteId,
                ]
            );

            return $proceed();
        }

        $isDigitalWallets = (bool)$lastQuote->getData('is_digital_wallets');

        // Only wallet quotes need Bold session cleanup; standard quotes follow Magento's default path.
        if (!$isDigitalWallets) {
            $this->sessionReuseLogger->info(
                'clearQuote: last quote is not a digital-wallet quote; proceeding with default Magento clearQuote()',
                [
                    'last_quote_id' => $lastQuoteId,
                    'current_quote_id' => $currentQuoteId,
                    'is_digital_wallets' => $isDigitalWallets,
                ]
            );

            return $proceed();
        }

        // Drop jwt_token / public_order_id so initCheckoutData() creates a fresh Bold order next time.
        $this->checkoutData->resetCheckoutData('clearQuote:digital_wallet_quote');

        $this->sessionReuseLogger->info(
            'clearQuote: reset Bold checkout session for digital-wallet quote; skipped default clearQuote()',
            [
                'last_quote_id' => $lastQuoteId,
                'current_quote_id' => $currentQuoteId,
                'cleared_public_order_id' => $publicOrderId,
            ]
        );

        // Skip proceed(): quote is already converted to an order and should not be reloaded.
        return $subject;
    }
}
