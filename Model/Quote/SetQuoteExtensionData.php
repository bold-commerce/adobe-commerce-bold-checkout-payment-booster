<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Quote;

use Bold\CheckoutPaymentBooster\Model\ResourceModel\Quote\QuoteExtensionData as QuoteExtensionDataResource;
use Exception;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

/**
 * Set quote extension data.
 */
class SetQuoteExtensionData
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
     * @var SerializerInterface
     */
    private $serializer;

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
        QuoteExtensionDataFactory $quoteExtensionDataFactory,
        QuoteExtensionDataResource $quoteExtensionDataResource,
        SerializerInterface $serializer,
        LoggerInterface $logger
    ) {
        $this->quoteExtensionDataFactory = $quoteExtensionDataFactory;
        $this->quoteExtensionDataResource = $quoteExtensionDataResource;
        $this->serializer = $serializer;
        $this->logger = $logger;
    }

    /**
     * Set quote extension data.
     *
     * @param int $quoteId
     * @param array $data
     * @return void
     */
    public function execute(int $quoteId, array $data): void
    {
        try {
            $quoteExtensionData = $this->quoteExtensionDataFactory->create();
            $this->quoteExtensionDataResource->load(
                $quoteExtensionData,
                $quoteId,
                QuoteExtensionDataResource::QUOTE_ID
            );
            if (!$quoteExtensionData->getId()) {
                $quoteExtensionData->setQuoteId($quoteId);
            }
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $value = $this->serializer->serialize($value);
                }
                $quoteExtensionData->setData($key, $value);
            }
            $this->quoteExtensionDataResource->save($quoteExtensionData);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }
}