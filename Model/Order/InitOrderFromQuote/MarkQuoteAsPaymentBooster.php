<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\InitOrderFromQuote;

use Bold\Checkout\Model\Quote\SetQuoteExtensionData;
use Bold\Checkout\Model\ResourceModel\Quote\QuoteExtensionData;
use Bold\CheckoutPaymentBooster\Model\Config as ModuleConfig;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Mark quote as Payment Booster.
 */
class MarkQuoteAsPaymentBooster implements OrderDataProcessorInterface
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
        if ($this->moduleConfig->isPaymentBoosterEnabled($websiteId)) {
            $this->setQuoteExtensionData->execute((int)$quote->getId(), [QuoteExtensionData::ORDER_CREATED => true]);
        }

        return $data;
    }
}
