<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\InitOrderFromQuote;

use Bold\Checkout\Model\Order\InitOrderFromQuote\OrderDataProcessorInterface;
use Bold\Checkout\Model\Quote\QuoteExtensionDataFactory;
use Bold\Checkout\Model\ResourceModel\Quote\QuoteExtensionData;
use Bold\CheckoutPaymentBooster\Model\Config;
use Exception;
use Magento\Quote\Api\Data\CartInterface;
use Psr\Log\LoggerInterface;

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
     * @var QuoteExtensionDataFactory
     */
    private $quoteExtensionDataFactory;

    /**
     * @var QuoteExtensionData
     */
    private $quoteExtensionDataResource;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Config $paymentBoosterConfig
     * @param QuoteExtensionDataFactory $quoteExtensionDataFactory
     * @param QuoteExtensionData $quoteExtensionDataResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $paymentBoosterConfig,
        QuoteExtensionDataFactory $quoteExtensionDataFactory,
        QuoteExtensionData $quoteExtensionDataResource,
        LoggerInterface $logger
    ) {
        $this->paymentBoosterConfig = $paymentBoosterConfig;
        $this->quoteExtensionDataFactory = $quoteExtensionDataFactory;
        $this->quoteExtensionDataResource = $quoteExtensionDataResource;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function process(array $data, CartInterface $quote): array
    {
        $websiteId = (int)$quote->getStore()->getWebsiteId();
        if (!$this->paymentBoosterConfig->isPaymentBoosterEnabled($websiteId)) {
            return $data;
        }
        try {
            $quoteExtensionData = $this->quoteExtensionDataFactory->create();
            $this->quoteExtensionDataResource->load(
                $quoteExtensionData,
                $quote->getId(),
                QuoteExtensionData::QUOTE_ID
            );
            if (!$quoteExtensionData->getId()) {
                $quoteExtensionData->setQuoteId((int)$quote->getId());
            }
            $quoteExtensionData->setOrderCreated(true);
            $this->quoteExtensionDataResource->save($quoteExtensionData);

            return $data;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

        return $data;
    }
}
