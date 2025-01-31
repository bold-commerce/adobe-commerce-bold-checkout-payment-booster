<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartInterfaceFactory;
use Magento\Quote\Model\Quote;

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

    public function __construct(
        CartInterfaceFactory $quoteFactory,
        CartRepositoryInterface $quoteRepository,
        SessionManagerInterface $customerSession,
        ManagerInterface $eventManager,
        DataObjectFactory $dataObjectFactory
    ) {
        $this->quoteFactory = $quoteFactory;
        $this->quoteRepository = $quoteRepository;
        $this->customerSession = $customerSession; // @phpstan-ignore-line
        $this->eventManager = $eventManager;
        $this->dataObjectFactory = $dataObjectFactory;
    }

    /**
     * @param int|string $storeId
     * @param ProductInterface $product
     * @param mixed[] $productRequestData
     * @return CartInterface
     * @throws LocalizedException
     */
    public function createQuote($storeId, ProductInterface $product, array $productRequestData): CartInterface
    {
        /** @var Quote $quote */
        $quote = $this->quoteFactory->create();
        $customer = $this->customerSession->getCustomer();

        $quote->setStoreId((int)$storeId);
        $quote->setData('is_digital_wallets', true);
        $quote->setCustomerIsGuest(true);
        $quote->setInventoryProcessed(false);

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
                $product, // @phpstan-ignore-line
                $this->dataObjectFactory->create(
                    [
                        'data' => array_merge(['qty' => 1], $productRequestData)
                    ]
                )
            );
        } catch (LocalizedException $e) {
            throw new LocalizedException(__('Could not add product to quote. Error: "%1"', $e->getMessage()));
        }

        $this->eventManager->dispatch(
            self::EVENT_PREFIX . 'after_add_digital_wallets_quote_product',
            [
                'product' => $product,
                'quote_item' => $quoteItem
            ]
        );

        if (is_string($quoteItem)) {
            throw new LocalizedException(__($quoteItem));
        }

        $quote->collectTotals();

        $this->eventManager->dispatch(
            self::EVENT_PREFIX . 'before_save_digital_wallets_product_quote',
            [
                'quote' => $quote
            ]
        );

        try {
            $this->quoteRepository->save($quote);
        } catch (LocalizedException $e) {
            throw new LocalizedException(__('Could not save quote. Error: "%1"', $e->getMessage()));
        }

        return $quote;
    }
}
