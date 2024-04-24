<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Order\InitOrderFromQuote;

use Bold\Checkout\Model\Order\InitOrderFromQuote\OrderDataProcessorInterface;
use Bold\Checkout\Model\Quote\QuoteExtensionDataFactory;
use Bold\Checkout\Model\ResourceModel\Quote\QuoteExtensionData;
use Bold\CheckoutPaymentBooster\Model\Config;
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Quote\Api\Data\CartInterface;
use Psr\Log\LoggerInterface;

/**
 * Mark order as created.
 */
class MarkOrderAsBooster implements OrderDataProcessorInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var Config
     */
    private $config;

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
     * @param Session $checkoutSession
     * @param Config $config
     * @param QuoteExtensionDataFactory $quoteExtensionDataFactory
     * @param QuoteExtensionData $quoteExtensionDataResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        Session                   $checkoutSession,
        Config                    $config,
        QuoteExtensionDataFactory $quoteExtensionDataFactory,
        QuoteExtensionData        $quoteExtensionDataResource,
        LoggerInterface           $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->config = $config;
        $this->quoteExtensionDataFactory = $quoteExtensionDataFactory;
        $this->quoteExtensionDataResource = $quoteExtensionDataResource;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function process(array $data, CartInterface $quote): array
    {
        $websiteId = $quote->getStore()->getWebsiteId();
        if (!$this->config->isPaymentBoosterEnabled((int)$websiteId)) {
            return $data;
        }
        try {
            $this->checkoutSession->setBoldCheckoutData($data);
            $quoteExtensionData = $this->quoteExtensionDataFactory->create();
            $quoteExtensionData->setQuoteId((int)$quote->getId());
            $quoteExtensionData->setOrderCreated(true);
            $this->quoteExtensionDataResource->save($quoteExtensionData);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
        return $data;
    }
}
