<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Cron\DigitalWallets;

use DateTimeImmutable;
use DateTimeZone;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\Collection as QuoteCollection;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

use function array_walk;

class DeactivateQuotes
{
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

    public function __construct(
        StoreManagerInterface $storeManager,
        QuoteCollectionFactory $quoteCollectionFactory,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->storeManager = $storeManager;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->scopeConfig = $scopeConfig;
    }

    public function execute(): void
    {
        $websites = $this->storeManager->getWebsites();

        array_walk(
            $websites,
            function (WebsiteInterface $website): void {
                $this->deactivateOldActiveQuotes($website->getId());
            }
        );
    }

    /**
     * @param int|string $websiteId
     */
    private function deactivateOldActiveQuotes($websiteId): void
    {
        /** @var QuoteCollection $quoteCollection */
        $quoteCollection = $this->quoteCollectionFactory->create();

        $quoteCollection->addFieldToFilter('is_digital_wallets', '1');
        $quoteCollection->addFieldToFilter('is_active', '1');
        $quoteCollection->addFieldToFilter('updated_at', ['gte' => $this->calculateOldestTime($websiteId)]);
        $quoteCollection->load();
        $quoteCollection->walk(
            static function (Quote $quote): void {
                $quote->setIsActive(false);
                $quote->save(); // @phpstan-ignore-line
            }
        );
    }

    private function calculateOldestTime($websiteId): string
    {
        /** @var string $retentionPeriod */
        $retentionPeriod = $this->scopeConfig->getValue(
            'checkout/bold_checkout_payment_booster_advanced/digital_wallets_quote_cleanup_retention_period',
            ScopeInterface::SCOPE_WEBSITES,
            $websiteId
        ) ?: '1';
        $oldestTime = new DateTimeImmutable("-$retentionPeriod hours", new DateTimeZone('UTC'));

        return $oldestTime->format('Y-m-d H:i:s');
    }
}
