<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Quote;

use Bold\CheckoutPaymentBooster\Model\ResourceModel\Quote\QuoteExtensionData as QuoteExtensionDataResource;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Get quote extension data.
 */
class GetQuoteExtensionData
{
    /**
     * @var QuoteExtensionDataFactory
     */
    private $quoteExtensionDataFactory;

    /**
     * @var QuoteExtensionDataResource
     */
    private $quoteExtensionDataResource;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param QuoteExtensionDataFactory $quoteExtensionDataFactory
     * @param QuoteExtensionDataResource $quoteExtensionDataResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        QuoteExtensionDataFactory  $quoteExtensionDataFactory,
        QuoteExtensionDataResource $quoteExtensionDataResource,
        LoggerInterface            $logger
    ) {
        $this->quoteExtensionDataFactory = $quoteExtensionDataFactory;
        $this->quoteExtensionDataResource = $quoteExtensionDataResource;
        $this->logger = $logger;
    }

    /**
     * Get quote extension data.
     *
     * @param int $quoteId
     * @return QuoteExtensionData
     */
    public function execute(int $quoteId): ?QuoteExtensionData
    {
        try {
            $quoteExtensionData = $this->quoteExtensionDataFactory->create();
            $this->quoteExtensionDataResource->load(
                $quoteExtensionData,
                $quoteId,
                QuoteExtensionDataResource::QUOTE_ID
            );

            return $quoteExtensionData->getQuoteId() ? $quoteExtensionData : null;
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

        return null;
    }
}
