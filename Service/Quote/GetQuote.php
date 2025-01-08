<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\Quote;


use Magento\Quote\Api\CartItemRepositoryInterface as QuoteItemRepository;
use Magento\Catalog\Helper\Product\ConfigurationPool;
use Bold\CheckoutPaymentBooster\Api\Quote\GetQuoteInterface;
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
     * @var ConfigurationPool
     */
    private $configurationPool;

    /**
     * @var \Magento\Catalog\Helper\Image
     */
    protected $imageHelper;

    public function __construct(
        Session $checkoutSession,
        QuoteIdToMaskedQuoteIdInterface $quoteIdToMaskedQuoteId,
        QuoteItemRepository $quoteItemRepository,
        SerializerInterface $serializer,
        ConfigurationPool $configurationPool,
        \Magento\Catalog\Helper\Image $imageHelper
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->quoteIdToMaskedQuoteId = $quoteIdToMaskedQuoteId;
        $this->quoteItemRepository = $quoteItemRepository;
        $this->serializer = $serializer;
        $this->configurationPool = $configurationPool;
        $this->imageHelper = $imageHelper;
    }

    /**
     * Gets Current Session Quote ID
     *
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getQuote()
    {
        $quoteId = (int)$this->checkoutSession->getQuote()->getId();
        $result['quoteId'] = $this->quoteIdToMaskedQuoteId->execute($quoteId);
        $result['quoteItemData'] = $this->getQuoteItemData();

        return $this->serializer->serialize($result);
    }

    /**
     * Retrieve quote item data
     *
     * @return array
     */
    private function getQuoteItemData()
    {
        $quoteItemData = [];
        $quoteId = $this->checkoutSession->getQuote()->getId();
        if ($quoteId) {
            $quoteItems = $this->quoteItemRepository->getList($quoteId);
            foreach ($quoteItems as $index => $quoteItem) {
                $quoteItemData[$index] = $quoteItem->toArray();
                $quoteItemData[$index]['options'] = $this->getFormattedOptionValue($quoteItem);
                $quoteItemData[$index]['thumbnail'] = $this->imageHelper->init(
                    $quoteItem->getProduct(),
                    'product_thumbnail_image'
                )->getUrl();
                $quoteItemData[$index]['message'] = $quoteItem->getMessage();
            }
        }
        return $quoteItemData;
    }

        /**
     * Retrieve formatted item options view
     *
     * @param \Magento\Quote\Api\Data\CartItemInterface $item
     * @return array
     */
    protected function getFormattedOptionValue($item)
    {
        $optionsData = [];
        $options = $this->configurationPool->getByProductType($item->getProductType())->getOptions($item);
        foreach ($options as $index => $optionValue) {
            /* @var $helper \Magento\Catalog\Helper\Product\Configuration */
            $helper = $this->configurationPool->getByProductType('default');
            $params = [
                'max_length' => 55,
                'cut_replacer' => ' <a href="#" class="dots tooltip toggle" onclick="return false">...</a>'
            ];
            $option = $helper->getFormattedOptionValue($optionValue, $params);
            $optionsData[$index] = $option;
            $optionsData[$index]['label'] = $optionValue['label'];
        }
        return $optionsData;
    }
}
