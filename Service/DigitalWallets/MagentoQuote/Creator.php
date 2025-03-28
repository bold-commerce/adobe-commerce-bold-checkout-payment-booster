<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote;

use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterfaceFactory;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;

use function __;
use function array_merge;
use function is_string;

/**
 * @api
 */
class Creator
{
    private const EVENT_PREFIX = 'bold_booster_';

    /**
     * @var CartInterfaceFactory
     */
    private $quoteFactory;
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;
    /**
     * @var AddressInterfaceFactory
     */
    private $quoteAddressFactory;
    /**
     * @var SessionManagerInterface&CustomerSession
     */
    private $customerSession;
    /**
     * @var ManagerInterface
     */
    private $eventManager;
    /**
     * @var DataObjectFactory
     */
    private $dataObjectFactory;
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    public function __construct(
        CartInterfaceFactory $quoteFactory,
        CartRepositoryInterface $quoteRepository,
        AddressInterfaceFactory $quoteAddressFactory,
        SessionManagerInterface $customerSession,
        ManagerInterface $eventManager,
        DataObjectFactory $dataObjectFactory,
        QuoteIdMaskFactory $quoteIdMaskFactory
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->quoteRepository = $quoteRepository;
        $this->quoteAddressFactory = $quoteAddressFactory;
        $this->customerSession = $customerSession;
        $this->eventManager = $eventManager;
        $this->dataObjectFactory = $dataObjectFactory;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
    }

    /**
     * @param int|string $storeId
     * @param ProductInterface $product
     * @param mixed[] $productRequestData
     * @return array{quote: CartInterface, maskedId: string|null}
     * @throws LocalizedException
     */
    public function createQuote($storeId, ProductInterface $product, array $productRequestData): array
    {
        /** @var Quote $quote */
        $quote = $this->quoteFactory->create();
        $customer = $this->customerSession->getCustomer();
        $boldPublicOrderId = $productRequestData['bold_order_id'] ?? '';

        unset($productRequestData['bold_order_id']);

        $quote->setStoreId((int)$storeId);
        $quote->setData('is_digital_wallets', true);
        $quote->setInventoryProcessed(false);
        $quote->setBillingAddress($this->quoteAddressFactory->create());
        $quote->setShippingAddress($this->quoteAddressFactory->create());
        $quote->setCustomerIsGuest(true);
        $quote->getExtensionAttributes()->setBoldOrderId($boldPublicOrderId);

        if ($customer !== null && $customer->getId() !== null) {
            $quote->setCustomer($customer->getDataModel());
            $quote->setCustomerIsGuest(false);
        }

        $this->eventManager->dispatch(
            self::EVENT_PREFIX . 'before_add_digital_wallets_quote_product',
            [
                'product' => $product,
                'product_request_data' => $productRequestData
            ]
        );

        try {
            $quoteItem = $quote->addProduct(
                $product,
                $this->dataObjectFactory->create(
                    [
                        'data' => array_merge(['qty' => 1], $productRequestData)
                    ]
                )
            );
        } catch (LocalizedException $localizedException) {
            throw new LocalizedException(
                __('Could not add product to quote. Error: "%1"', $localizedException->getMessage())
            );
        }

        if (is_string($quoteItem)) {
            throw new LocalizedException(__($quoteItem));
        }

        $this->eventManager->dispatch(
            self::EVENT_PREFIX . 'after_add_digital_wallets_quote_product',
            [
                'product' => $product,
                'quote_item' => $quoteItem
            ]
        );

        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->collectTotals();

        $this->eventManager->dispatch(
            self::EVENT_PREFIX . 'before_save_digital_wallets_product_quote',
            [
                'quote' => $quote
            ]
        );

        try {
            $this->quoteRepository->save($quote);
        } catch (LocalizedException $localizedException) {
            throw new LocalizedException(
                __('Could not save quote. Error: "%1"', $localizedException->getMessage()),
                $localizedException
            );
        }

        if ($customer === null || $customer->getId() === null) {
            $quoteIdMask = $this->createQuoteIdMask($quote);
            $maskedId = $quoteIdMask->getMaskedId();
        } else {
            $maskedId = null;
        }

        return [
            'quote' => $quote,
            'maskedId' => $maskedId
        ];
    }

    /**
     * @param Quote $quote
     * @return QuoteIdMask
     * @throws LocalizedException
     */
    private function createQuoteIdMask(Quote $quote): QuoteIdMask
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
     * @param Quote $quote
     * @return void
     * @throws LocalizedException
     */
    private function deactivateQuote(Quote $quote): void
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
