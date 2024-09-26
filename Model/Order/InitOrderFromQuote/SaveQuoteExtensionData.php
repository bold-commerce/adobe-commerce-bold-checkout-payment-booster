<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\InitOrderFromQuote;

use Bold\CheckoutPaymentBooster\Model\Config as ModuleConfig;
use Bold\CheckoutPaymentBooster\Model\Quote\SetQuoteExtensionData;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\Quote\QuoteExtensionData;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Add public order id to quote extension data.
 */
class SaveQuoteExtensionData implements OrderDataProcessorInterface
{
    /**
     * @var SetQuoteExtensionData
     */
    private $setQuoteExtensionData;

    /**
     * @param ModuleConfig $moduleConfig
     * @param SetQuoteExtensionData $setQuoteExtensionData
     */
    public function __construct(
        SetQuoteExtensionData $setQuoteExtensionData
    ) {
        $this->setQuoteExtensionData = $setQuoteExtensionData;
    }

    /**
     * @inheritDoc
     */
    public function process(array $data, CartInterface $quote): array
    {
        $this->setQuoteExtensionData->execute(
            (int)$quote->getId(),
            [
                QuoteExtensionData::PUBLIC_ID => $data['data']['public_order_id'] ?? null,
                QuoteExtensionData::FLOW_SETTINGS => $data['data']['flow_settings'] ?? null,
            ]
        );

        return $data;
    }
}
