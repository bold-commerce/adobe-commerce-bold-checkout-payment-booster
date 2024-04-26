<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\InitOrderFromQuote;

use Bold\Checkout\Model\Order\InitOrderFromQuote\OrderDataProcessorInterface;
use Bold\Checkout\Model\Quote\SetQuoteExtensionData;
use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Mark quote as a payment booster.
 */
class MarkQuoteAsPaymentBooster implements OrderDataProcessorInterface
{
    /**
     * @var Config
     */
    private $paymentBoosterConfig;

    /**
     * @var SetQuoteExtensionData
     */
    private $setQuoteExtensionData;

    /**
     * @param Config $paymentBoosterConfig
     * @param SetQuoteExtensionData $setQuoteExtensionData
     */
    public function __construct(
        Config $paymentBoosterConfig,
        SetQuoteExtensionData $setQuoteExtensionData
    ) {
        $this->paymentBoosterConfig = $paymentBoosterConfig;
        $this->setQuoteExtensionData = $setQuoteExtensionData;
    }

    /**
     * @inheritDoc
     */
    public function process(array $data, CartInterface $quote): array
    {
        $websiteId = (int)$quote->getStore()->getWebsiteId();
        if ($this->paymentBoosterConfig->isPaymentBoosterEnabled($websiteId)) {
            $this->setQuoteExtensionData->execute((int)$quote->getId(), true);
        }

        return $data;
    }
}
