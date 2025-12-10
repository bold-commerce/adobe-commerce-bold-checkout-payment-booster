<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Plugin\Quote\Api;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Api\Data\CartItemExtensionFactory;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Model\Quote\Item as QuoteItem;

/**
 * Plugin to add product_id to cart item extension attributes.
 *
 * Intercepts cart retrieval to populate product_id from the underlying
 * model into the extension_attributes for API responses.
 */
class AddProductIdToCartItemPlugin
{
    /**
     * @var CartItemExtensionFactory
     */
    private $cartItemExtensionFactory;

    /**
     * @param CartItemExtensionFactory $cartItemExtensionFactory
     */
    public function __construct(CartItemExtensionFactory $cartItemExtensionFactory)
    {
        $this->cartItemExtensionFactory = $cartItemExtensionFactory;
    }

    /**
     * Add product_id to cart items after cart is retrieved.
     *
     * @param CartRepositoryInterface $subject
     * @param CartInterface $cart
     * @return CartInterface
     */
    public function afterGet(CartRepositoryInterface $subject, CartInterface $cart): CartInterface
    {
        $items = $cart->getItems();
        if ($items === null) {
            return $cart;
        }

        foreach ($items as $item) {
            $this->addProductIdToItem($item);
        }

        return $cart;
    }

    /**
     * Add product_id to cart items after active cart is retrieved.
     *
     * @param CartRepositoryInterface $subject
     * @param CartInterface $cart
     * @return CartInterface
     */
    public function afterGetActive(CartRepositoryInterface $subject, CartInterface $cart): CartInterface
    {
        $items = $cart->getItems();
        if ($items === null) {
            return $cart;
        }

        foreach ($items as $item) {
            $this->addProductIdToItem($item);
        }

        return $cart;
    }

    /**
     * Add product_id to cart items after active cart is retrieved for customer.
     *
     * @param CartRepositoryInterface $subject
     * @param CartInterface $cart
     * @return CartInterface
     */
    public function afterGetActiveForCustomer(CartRepositoryInterface $subject, CartInterface $cart): CartInterface
    {
        $items = $cart->getItems();
        if ($items === null) {
            return $cart;
        }

        foreach ($items as $item) {
            $this->addProductIdToItem($item);
        }

        return $cart;
    }

    /**
     * Populate product_id in item extension attributes.
     *
     * @param CartItemInterface $item
     * @return void
     */
    private function addProductIdToItem(CartItemInterface $item): void
    {
        // Only process if the item is a concrete Quote Item model
        if (!$item instanceof QuoteItem) {
            return;
        }

        $extensionAttributes = $item->getExtensionAttributes();
        if ($extensionAttributes === null) {
            $extensionAttributes = $this->cartItemExtensionFactory->create();
        }

        // Get product_id from the model (uses magic getter)
        $productId = $item->getProductId();
        if ($productId !== null) {
            $extensionAttributes->setProductId((int)$productId);
            $item->setExtensionAttributes($extensionAttributes);
        }
    }
}

