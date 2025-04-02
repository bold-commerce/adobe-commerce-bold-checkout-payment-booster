<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\DigitalWallets\MagentoQuote;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\Collection as QuoteCollection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;
use Psr\Log\LoggerInterface;

use function __;
use function array_walk;
use function count;
use function gmdate;
use function implode;

class Deactivator
{
    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var QuoteCollectionFactory
     */
    private $quoteCollectionFactory;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        CartRepositoryInterface $quoteRepository,
        StoreManagerInterface $storeManager,
        QuoteCollectionFactory $quoteCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->storeManager = $storeManager;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
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

    /**
     * @return int[][]
     */
    public function deactivateAllQuotes(): array
    {
        /** @var Website[] $websites */
        $websites = $this->storeManager->getWebsites();
        $deactivatedQuoteIdsByWebsite = [];

        array_walk(
            $websites,
            function (Website $website) use (&$deactivatedQuoteIdsByWebsite): void {
                $deactivatedQuoteIds = $this->deactivateOldActiveQuotes($website);

                if (count($deactivatedQuoteIds) === 0) {
                    return;
                }

                $deactivatedQuoteIdsByWebsite[$website->getId()] = $deactivatedQuoteIds;
            }
        );

        return $deactivatedQuoteIdsByWebsite;
    }

    /**
     * @return int[]
     */
    private function deactivateOldActiveQuotes(Website $website): array
    {
        $storeIds = $website->getStoreIds();
        /** @var QuoteCollection $quoteCollection */
        $quoteCollection = $this->quoteCollectionFactory->create();
        /** @var int|string $websiteId */
        $websiteId = $website->getId();
        $deactivatedQuoteIds = [];

        $quoteCollection->addFieldToFilter('store_id', ['in' => $storeIds]);
        $quoteCollection->addFieldToFilter('is_digital_wallets', '1');
        $quoteCollection->addFieldToFilter('is_active', '1');
        $quoteCollection->addFieldToFilter('updated_at', ['lteq' => $this->calculateOldestTime($websiteId)]);
        $quoteCollection->load();
        $quoteCollection->walk(
            function (Quote $quote) use (&$deactivatedQuoteIds): void {
                $quote->setIsActive(false);

                try {
                    $quote->save();

                    $deactivatedQuoteIds[] = $quote->getId();
                } catch (Exception $exception) {
                    $this->logger->error(
                        __('Could not deactivate Digital Wallets quote with ID "%1".', $quote->getId()),
                        [
                            'exception' => $exception
                        ]
                    );
                }
            }
        );

        if (count($deactivatedQuoteIds) === 0) {
            return $deactivatedQuoteIds;
        }

        $this->logger->info(
            __(
                'Deactivated %total_quotes Digital Wallets {total_quotes, plural, =1 {quote with ID} other '
                    . '{quotes with IDs}} "%quote_ids" in website "%website_id".',
                [
                    'total_quotes' => count($deactivatedQuoteIds),
                    'quote_ids' => implode(', ', $deactivatedQuoteIds),
                    'website_id' => $websiteId
                ]
            )
        );

        return $deactivatedQuoteIds;
    }

    /**
     * @param int|string $websiteId
     */
    private function calculateOldestTime($websiteId): string
    {
        /** @var string $retentionPeriod */
        $retentionPeriod = $this->scopeConfig->getValue(
            'checkout/bold_checkout_payment_booster_advanced/digital_wallets_quote_cleanup_retention_period',
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        ) ?: '1';

        try {
            $oldestTime = new DateTimeImmutable("-$retentionPeriod hours", new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            return gmdate('Y-m-d H:i:s');
        }

        return $oldestTime->format('Y-m-d H:i:s');
    }
}
