<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\InitOrderFromQuote;

use Bold\CheckoutPaymentBooster\Model\Config as ModuleConfig;
use Bold\CheckoutPaymentBooster\Model\Quote\SetQuoteExtensionData;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\Quote\QuoteExtensionData;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Mark quote as Fastlane.
 */
class MarkQuoteAsFastlane implements OrderDataProcessorInterface
{
    /**
     * @var ModuleConfig
     */
    private $moduleConfig;

    /**
     * @var SetQuoteExtensionData
     */
    private $setQuoteExtensionData;

    /**
     * @param ModuleConfig $moduleConfig
     * @param SetQuoteExtensionData $setQuoteExtensionData
     */
    public function __construct(
        ModuleConfig $moduleConfig,
        SetQuoteExtensionData $setQuoteExtensionData
    ) {
        $this->moduleConfig = $moduleConfig;
        $this->setQuoteExtensionData = $setQuoteExtensionData;
    }

    /**
     * @inheritDoc
     */
    public function process(array $data, CartInterface $quote): array
    {
        $websiteId = (int)$quote->getStore()->getWebsiteId();
        if ($this->moduleConfig->isPaymentBoosterEnabled($websiteId)
            && $this->moduleConfig->isFastlaneEnabled($websiteId)) {
            $this->setQuoteExtensionData->execute((int)$quote->getId(), [QuoteExtensionData::ORDER_CREATED => true]);
        }

        return $data;
    }
}
