<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\Integration\MagentoQuote;

use Exception;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;

use function __;

/**
 * @api
 */
class Create
{
    /**
     * @var CartInterfaceFactory
     */
    private $quoteFactory;
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;
    /**
     * @var CartTotalRepositoryInterface
     */
    private $quoteTotalRepository;
    /**
     * @var AddressInterfaceFactory
     */
    private $quoteAddressFactory;
    /**
     * @var SessionManagerInterface&CustomerSession
     */
    private $customerSession;
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    public function __construct(
        CartInterfaceFactory $quoteFactory,
        CartRepositoryInterface $quoteRepository,
        CartTotalRepositoryInterface $quoteTotalRepository,
        AddressInterfaceFactory $quoteAddressFactory,
        SessionManagerInterface $customerSession,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->quoteRepository = $quoteRepository;
        $this->quoteTotalRepository = $quoteTotalRepository;
        $this->quoteAddressFactory = $quoteAddressFactory;
        $this->customerSession = $customerSession;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * @param int $storeId
     * @param string $boldPublicOrderId
     * @return CartInterface
     * @throws LocalizedException
     */
    public function createQuote(int $storeId, string $boldPublicOrderId): CartInterface
    {
        /** @var Quote $quote */
        $quote = $this->quoteFactory->create();
        $customer = $this->customerSession->getCustomer();

        $quote
            ->setStoreId($storeId)
            ->setBillingAddress($this->quoteAddressFactory->create())
            ->setShippingAddress($this->quoteAddressFactory->create())
            ->setInventoryProcessed(false);
        $quote->getExtensionAttributes()->setBoldOrderId($boldPublicOrderId);
//        $quote->getExtensionAttributes()->setIsBoldIntegrationCart(true); // TODO: Implement the Extension Attribute

        if ($customer !== null && $customer->getId() !== null) {
            $quote->setCustomer($customer->getDataModel());
            $quote->setCustomerIsGuest(false);
        } else {
            $quote->setCustomerIsGuest(true);
        }

        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote = $this->saveQuote($quote);

        return $quote;
    }

    /**
     * @param CartInterface $quote
     * @return QuoteIdMask
     * @throws LocalizedException
     */
    public function createQuoteIdMask(CartInterface $quote): QuoteIdMask
    {
        /** @var QuoteIdMask $quoteIdMask */
        $quoteIdMask = $this->quoteIdMaskFactory->create();

        try {
            $quoteIdMask->setQuoteId($quote->getId())
                ->save();
        } catch (Exception $exception) {
            $this->deactivateQuote($quote);

            throw new LocalizedException(
                __(
                    'Could not create mask identifier for quote with identifier "%1". Error: "%2"',
                    $quote->getId(),
                    $exception->getMessage()
                ),
                $exception
            );
        }

        return $quoteIdMask;
    }

    /**
     * @param CartInterface $quote
     * @return CartInterface
     * @throws LocalizedException
     */
    public function saveQuote(CartInterface $quote): CartInterface
    {
        try {
            /** @var Quote $quote */
            $quote->collectTotals();
            $this->quoteRepository->save($quote);
            $quote = $this->quoteRepository->get($quote->getId());
        } catch (LocalizedException $localizedException) {
            throw new LocalizedException(
                __('Could not save quote. Error: "%1"', $localizedException->getMessage()),
                $localizedException
            );
        }

        return $quote;
    }

    /**
     * @param CartInterface $quote
     * @return \Magento\Quote\Api\Data\TotalsInterface
     * @throws LocalizedException
     */
    public function getQuoteTotals(CartInterface $quote): \Magento\Quote\Api\Data\TotalsInterface
    {
        try {
            $totals = $this->quoteTotalRepository->get($quote->getId());
        } catch (LocalizedException $localizedException) {
            throw new LocalizedException(
                __('Could not get quote totals. Error: "%1"', $localizedException->getMessage()),
                $localizedException
            );
        }

        return $totals;
    }

    /**
     * @param CartInterface $quote
     * @return void
     * @throws LocalizedException
     */
    private function deactivateQuote(CartInterface $quote): void
    {
        $quote->setIsActive(false);

        try {
            $this->quoteRepository->save($quote);
        } catch (LocalizedException $localizedException) {
            throw new LocalizedException(
                __('Could not deactivate quote. Error: "%1"', $localizedException->getMessage()),
                $localizedException
            );
        }
    }
}
