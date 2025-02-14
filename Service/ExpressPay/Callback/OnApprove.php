<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\ExpressPay\Callback;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;

class OnApprove
{
    /**
     * @var MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var SessionManagerInterface
     */
    private $checkoutSession;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var DataPersistorInterface
     */
    private $dataPersistor;

    /**
     * @var LoggerInterface
     */
    private $logger;


    public function __construct(
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        SessionManagerInterface         $checkoutSession,
        CartRepositoryInterface         $cartRepository,
        DataPersistorInterface          $dataPersistor,
        LoggerInterface                 $logger,
    ) {
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
        $this->dataPersistor = $dataPersistor;
        $this->logger = $logger;
    }

    /**
     * @param string|int $quoteMaskId
     * @param string $gatewayId
     * @param string $paypalOrderId
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute($quoteMaskId, $gatewayId, $paypalOrderId): void
    {
        if (!is_numeric($quoteMaskId) && strlen($quoteMaskId) === 32) {
            try {
                $quoteId = $this->maskedQuoteIdToQuoteId->execute($quoteMaskId);
            } catch (NoSuchEntityException $noSuchEntityException) {
                throw new LocalizedException(
                    __('Active quote not found. Invalid quote mask ID "%1".', $quoteMaskId)
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
                throw new LocalizedException(__('Active quote not found. Invalid quote ID "%1".', $quoteId));
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

        $this->dataPersistor->set('skip_recaptcha', true);
    }
}
