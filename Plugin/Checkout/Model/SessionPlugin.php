<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Plugin\Checkout\Model;

use Bold\CheckoutPaymentBooster\Model\CheckoutData;
use Closure;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

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

    public function __construct(
        CartRepositoryInterface $quoteRepository,
        CheckoutData $checkoutData
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->checkoutData = $checkoutData;
    }

    public function aroundClearQuote(Session $subject, Closure $proceed): Session
    {
        $lastQuoteId = $subject->getLastQuoteId();

        if ($lastQuoteId === null) {
            return $proceed();
        }

        try {
            /** @var CartInterface&Quote $lastQuote */
            $lastQuote = $this->quoteRepository->get($lastQuoteId);
        } catch (NoSuchEntityException $e) {
            return $proceed();
        }

        if (!$lastQuote->getData('is_digital_wallets')) {
            return $proceed();
        }

        $this->checkoutData->resetCheckoutData();

        return $subject;
    }
}
