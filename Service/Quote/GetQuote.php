<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\Quote;

use Bold\CheckoutPaymentBooster\Api\Quote\GetQuoteInterface;
use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Checkout\Model\Session;
use Magento\Quote\Model\QuoteIdToMaskedQuoteIdInterface;

/**
 * Get Current Quote ID from Magento quote.
 */
class GetQuote implements GetQuoteInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var QuoteIdToMaskedQuoteId
     */
    private $quoteIdToMaskedQuoteId;    

    /**
     * @var QuoteItemRepository
     */
    private $quoteItemRepository;
    
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var DefaultConfigProvider
     */
    private $defaultConfig;

    /**
     * @var \Magento\Catalog\Helper\Image
     */
    protected $imageHelper;

    public function __construct(
        Session $checkoutSession,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        SerializerInterface $serializer,
        DefaultConfigProvider $defaultConfig

    ) {
        $this->checkoutSession = $checkoutSession;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->serializer = $serializer;
        $this->defaultConfig = $defaultConfig;
    }

    /**
     * Gets Current Session Quote ID
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getQuote(): string
    {
        $quoteId = (int)$this->checkoutSession->getQuote()->getId();
        $result['quoteId'] = $this->quoteIdToMaskedQuoteId->execute($quoteId);
        $result['checkoutConfig'] = $this->defaultConfig->getConfig();

        return $this->serializer->serialize($result);
    }
}
