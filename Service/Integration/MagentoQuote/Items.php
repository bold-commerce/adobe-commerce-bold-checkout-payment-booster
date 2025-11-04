<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\Integration\MagentoQuote;

use Exception;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

use function __;

/**
 * Service class for managing quote items (add, update, remove).
 *
 * @api
 */
class Items
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        ProductRepositoryInterface $productRepository
    ) {
        $this->productRepository = $productRepository;
    }

    /**
     * Add products to quote.
     *
     * @param CartInterface $quote
     * @param array<int, array<string, mixed>> $items
     * @param int $storeId
     * @return CartInterface
     * @throws LocalizedException
     */
    public function addProductsToQuote(CartInterface $quote, array $items, int $storeId): CartInterface
    {
        if (empty($items)) {
            throw new LocalizedException(__('Items array cannot be empty.'));
        }

        try {
            /** @var Quote $quote */
            // Create a hash map of existing quote items indexed by product ID for validation
            $existingProductIds = [];
            foreach ($quote->getAllItems() as $existingItem) {
                $existingProductIds[$existingItem->getProduct()->getId()] = true;
            }

            foreach ($items as $item) {
                if (!isset($item['quantity'])) {
                    throw new LocalizedException(__('Each item must include a quantity.'));
                }

                $product = $this->getProduct($item, $storeId);
                $productId = $product->getId();

                // Check if product already exists in quote
                if (isset($existingProductIds[$productId])) {
                    throw new LocalizedException(
                        __(
                            'Product with SKU "%1" is already in the quote. Use the update items endpoint to change the quantity.',
                            $product->getSku()
                        )
                    );
                }
                
                $quote->addProduct($product, (float)$item['quantity']);
            }

            return $quote;
        } catch (Exception $e) {
            throw new LocalizedException(
                __('Could not add products to quote. Error: "%1"', $e->getMessage()),
                $e
            );
        }
    }

    /**
     * Update quantities of existing products in quote.
     *
     * @param CartInterface $quote
     * @param array<int, array<string, mixed>> $items
     * @param int $storeId
     * @return CartInterface
     * @throws LocalizedException
     */
    public function updateQuoteItemQuantities(CartInterface $quote, array $items, int $storeId): CartInterface
    {
        if (empty($items)) {
            throw new LocalizedException(__('Items array cannot be empty.'));
        }

        try {
            /** @var Quote $quote */
            // Create a hash map of quote items indexed by product ID for O(1) lookup
            $quoteItemsByProductId = [];
            foreach ($quote->getAllItems() as $existingItem) {
                $quoteItemsByProductId[$existingItem->getProduct()->getId()] = $existingItem;
            }

            foreach ($items as $item) {
                if (!isset($item['quantity'])) {
                    throw new LocalizedException(__('Each item must include a quantity.'));
                }

                $product = $this->getProduct($item, $storeId);
                $productId = $product->getId();
                $quantity = (float)$item['quantity'];

                // Find the quote item using hash map lookup
                if (!isset($quoteItemsByProductId[$productId])) {
                    throw new LocalizedException(
                        __(
                            'Product with SKU "%1" is not in the quote. Use the add items endpoint to add new products.',
                            $product->getSku()
                        )
                    );
                }

                $quoteItem = $quoteItemsByProductId[$productId];

                // Update the quantity
                if ($quantity > 0) {
                    $quoteItem->setQty($quantity);
                } else {
                    throw new LocalizedException(
                        __(
                            'Quantity must be greater than 0. Use the remove items endpoint to remove products from the quote.'
                        )
                    );
                }
            }

            return $quote;
        } catch (Exception $e) {
            throw new LocalizedException(
                __('Could not update quote item quantities. Error: "%1"', $e->getMessage()),
                $e
            );
        }
    }

    /**
     * Remove products from quote.
     *
     * @param CartInterface $quote
     * @param array<int, array<string, mixed>> $items
     * @param int $storeId
     * @return CartInterface
     * @throws LocalizedException
     */
    public function removeProductsFromQuote(CartInterface $quote, array $items, int $storeId): CartInterface
    {
        if (empty($items)) {
            throw new LocalizedException(__('Items array cannot be empty.'));
        }

        try {
            /** @var Quote $quote */
            // Create a hash map of quote items indexed by product ID for O(1) lookup
            $quoteItemsByProductId = [];
            foreach ($quote->getAllItems() as $existingItem) {
                $quoteItemsByProductId[$existingItem->getProduct()->getId()] = $existingItem;
            }

            foreach ($items as $item) {
                $product = $this->getProduct($item, $storeId);
                $productId = $product->getId();

                // Find the quote item using hash map lookup
                if (!isset($quoteItemsByProductId[$productId])) {
                    throw new LocalizedException(
                        __(
                            'Product with SKU "%1" is not in the quote and cannot be removed.',
                            $product->getSku()
                        )
                    );
                }

                $quoteItem = $quoteItemsByProductId[$productId];

                // Remove the item from quote
                $quote->removeItem($quoteItem->getId());
            }

            return $quote;
        } catch (Exception $e) {
            throw new LocalizedException(
                __('Could not remove products from quote. Error: "%1"', $e->getMessage()),
                $e
            );
        }
    }

    /**
     * Get product by SKU or product ID.
     *
     * @param array<string, mixed> $item
     * @param int $storeId
     * @return Product
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getProduct(array $item, int $storeId): Product
    {
        // product_id takes precedence over sku
        if (isset($item['product_id'])) {
            /** @var Product $product */
            $product = $this->productRepository->getById($item['product_id'], false, $storeId);
        } elseif (isset($item['sku'])) {
            /** @var Product $product */
            $product = $this->productRepository->get($item['sku'], false, $storeId);
        } else {
            throw new LocalizedException(__('Each item must include either \'sku\' or \'product_id\'.'));
        }

        return $product;
    }
}

