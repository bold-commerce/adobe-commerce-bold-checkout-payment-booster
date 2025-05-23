<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\Quote;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Image\UrlBuilder;
use Magento\Directory\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Cart line items builder.
 */
class GetCartLineItems
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var UrlBuilder
     */
    private $productUrlBuilder;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     * @param UrlBuilder $productUrlBuilder
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ProductRepositoryInterface $productRepository,
        StoreManagerInterface $storeManager,
        UrlBuilder $productUrlBuilder
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->productUrlBuilder = $productUrlBuilder;
    }

    /**
     * Extract line items data.
     *
     * @param CartInterface&Quote $quote
     * @return array<array{
     *     id: int,
     *     quantity: int,
     *     title: string,
     *     product_title: string,
     *     weight: float,
     *     taxable: bool,
     *     image: string,
     *     requires_shipping: bool,
     *     line_item_key: string,
     *     price: int
     * }>
     * @throws LocalizedException
     */
    public function getItems(CartInterface $quote): array
    {
        $lineItems = [];
        /** @var CartItemInterface&Item $cartItem */
        foreach ($quote->getAllVisibleItems() as $cartItem) {
            $lineItems[] = $this->getLineItem($cartItem);
        }
        if (!$lineItems) {
            throw new LocalizedException(__('There are no cart items to checkout.'));
        }
        return $lineItems;
    }

    /**
     * Extract quote item entity data into array.
     *
     * @param CartItemInterface&Item $item
     * @return array{
     *     id: int,
     *     quantity: int,
     *     title: string,
     *     product_title: string,
     *     weight: float,
     *     taxable: bool,
     *     image: string,
     *     requires_shipping: bool,
     *     line_item_key: string,
     *     price: int
     * }
     */
    private function getLineItem(CartItemInterface $item): array
    {
        return [
            'id' => (int)$item->getProduct()->getId(),
            'quantity' => $this->extractLineItemQuantity($item),
            'title' => $this->getLineItemName($item),
            'product_title' => $this->getLineItemName($item),
            'weight' => $this->getLineItemWeightInGrams($item),
            'taxable' => true, // Doesn't matter since RSA will handle taxes
            'image' => $this->getLineItemImage($item),
            'requires_shipping' => !$item->getProduct()->getIsVirtual(),
            'line_item_key' => (string)$item->getId(),
            'price' => $this->getLineItemPrice($item),
        ];
    }

    /**
     * Gets the product's name from the line item
     *
     * @param CartItemInterface&Item $item
     * @return string
     */
    private function getLineItemName(CartItemInterface $item): string
    {
        $item = $item->getParentItem() ?: $item;
        return $item->getName();
    }

    /**
     * Gets the price of a line item
     *
     * @param CartItemInterface&Item $item
     * @return int
     */
    private function getLineItemPrice(CartItemInterface $item): int
    {
        $item = $item->getParentItem() ?: $item;
        return $this->convertToCents((float)$item->getPrice());
    }

    /**
     * Gets the weight of a line item in grams
     *
     * @param CartItemInterface&Item $item
     * @return float
     */
    private function getLineItemWeightInGrams(CartItemInterface $item): float
    {
        $unit = strtolower(
            $this->scopeConfig->getValue(Data::XML_PATH_WEIGHT_UNIT, ScopeInterface::SCOPE_STORE)
        );
        $weight = $item->getWeight();
        if ($unit === 'kgs') {
            return round($weight * 1000, 2);
        } elseif ($unit === 'lbs') {
            return round($weight * 453.59237, 2);
        }

        return $weight;
    }

    /**
     * Gets the line item's image. Falls back to the parent item (If available) if the direct
     * item does not have an image
     *
     * @param CartItemInterface&Item $item
     * @return string
     */
    private function getLineItemImage(CartItemInterface $item): string
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        /** @var ProductInterface&Product $product */
        $product = $this->productRepository->getById($item->getProductId());
        if ($product->getImage() && $product->getImage() !== 'no_selection') {
            return $baseUrl . 'catalog/product' . $product->getImage();
        }
        // Attempting to get the parent product if there is one
        if ($item->getParentItem()) {
            /** @var ProductInterface&Product $parentProduct */
            $parentProduct = $this->productRepository->getById($item->getParentItem()->getProductId());
            return $baseUrl . 'catalog/product' . $parentProduct->getImage();
        }
        return $this->productUrlBuilder->getUrl('no_selection', 'product_thumbnail_image');
    }

    /**
     * Get quote item quantity considering product type.
     *
     * @param CartItemInterface&Item $item
     * @return int
     */
    private function extractLineItemQuantity(CartItemInterface $item): int
    {
        $parentItem = $item->getParentItem();
        if ($parentItem) {
            $item = $parentItem;
        }
        return (int)$item->getQty();
    }

    /**
     * Converts a dollar amount to cents
     *
     * @param string|float $dollars
     * @return integer
     */
    private function convertToCents($dollars): int
    {
        return (int)round(floatval($dollars) * 100);
    }
}
