<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;

use function __;

class Deactivator
{
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    public function __construct(CartRepositoryInterface $quoteRepository)
    {
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param int|string $quoteId
     * @return void
     * @throws LocalizedException
     */
    public function deactivateQuote($quoteId): void
    {
        try {
            /** @var Quote $quote */
            $quote = $this->quoteRepository->get((int)$quoteId);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('Invalid quote identifier "%1".', $quoteId));
        }

        if (!$quote->getIsActive()) {
            return;
        }

        /** @var bool|int|null $isDigitalWalletsQuote */
        $isDigitalWalletsQuote = $quote->getData('is_digital_wallets');

        if (!$isDigitalWalletsQuote) {
            throw new LocalizedException(__('Quote with identifier "%1" is not a Digital Wallets quote.', $quoteId));
        }

        $quote->setIsActive(false);

        try {
            $this->quoteRepository->save($quote);
        } catch (LocalizedException $localizedException) {
            throw new LocalizedException(__('Could not deactivate quote with identifier "%1".', $quoteId));
        }
    }
}
