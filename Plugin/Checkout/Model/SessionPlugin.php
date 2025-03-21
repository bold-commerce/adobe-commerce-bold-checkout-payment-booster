<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Plugin\Checkout\Model;

use Closure;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;

class SessionPlugin
{
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    public function __construct(CartRepositoryInterface $quoteRepository)
    {
        $this->quoteRepository = $quoteRepository;
    }

    public function aroundClearQuote(Session $subject, Closure $proceed): Session
    {
        $lastQuoteId = $subject->getLastQuoteId();

        if ($lastQuoteId === null) {
            return $proceed();
        }

        try {
            $lastQuote = $this->quoteRepository->get($lastQuoteId);
        } catch (NoSuchEntityException $e) {
            return $proceed();
        }

        if (!$lastQuote->getData('is_digital_wallets')) {
            return $proceed();
        }

        return $subject;
    }
}
