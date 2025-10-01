<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api;

use Bold\CheckoutPaymentBooster\Api\Data\MagentoQuoteBoldOrderInterface;
use Magento\Sales\Api\Data\OrderInterface;

interface MagentoQuoteBoldOrderRepositoryInterface
{
    /**
     * Retrieves the MagentoQuoteBoldOrderInterface instance for the specified ID.
     *
     * @param mixed $id The identifier of the order to retrieve.
     * @return MagentoQuoteBoldOrderInterface The order instance associated with the provided ID.
     */
    public function get($id): MagentoQuoteBoldOrderInterface;

    /**
     * Retrieves the MagentoQuoteBoldOrderInterface instance for the specified quote ID.
     *
     * @param mixed $quoteId The identifier of the quote to retrieve.
     * @return MagentoQuoteBoldOrderInterface The order instance associated with the provided quote ID.
     */
    public function getByQuoteId($quoteId): MagentoQuoteBoldOrderInterface;

    /**
     * Retrieves the public order ID associated with the provided order.
     *
     * @param OrderInterface $order The order instance from which to retrieve the public order ID.
     * @return string|null The public order ID associated with the given order.
     */
    public function getPublicOrderIdFromOrder(OrderInterface $order): ?string;

    /**
     * Retrieves the MagentoQuoteBoldOrderInterface instance for the given Bold order ID.
     *
     * @param string $boldOrderId The Bold order ID used to identify the order.
     * @return MagentoQuoteBoldOrderInterface The order instance associated with the specified Bold order ID.
     */
    public function getByBoldOrderId(string $boldOrderId): MagentoQuoteBoldOrderInterface;

    /**
     * Saves the provided MagentoQuoteBoldOrderInterface instance.
     *
     * @param MagentoQuoteBoldOrderInterface $magentoQuoteBoldOrder The order instance to be saved.
     * @return void
     */
    public function save(MagentoQuoteBoldOrderInterface $magentoQuoteBoldOrder): void;

    /**
     * Deletes the specified MagentoQuoteBoldOrderInterface instance.
     *
     * @param MagentoQuoteBoldOrderInterface $magentoQuoteBoldOrder The order instance to be deleted.
     * @return void
     */
    public function delete(MagentoQuoteBoldOrderInterface $magentoQuoteBoldOrder): void;

    /**
     * Deletes the resource associated with the specified ID.
     *
     * @param mixed $magentoQuoteBoldOrderId The identifier of the resource to delete.
     * @return void
     */
    public function deleteById($magentoQuoteBoldOrderId): void;

    /**
     * Find Or Create Bold Quote Public Order Relation by Quote ID
     *
     * @param string $quoteId
     * @return MagentoQuoteBoldOrderInterface
     */
    public function findOrCreateByQuoteId(string $quoteId): MagentoQuoteBoldOrderInterface;

    /**
     * Is Quote ID Processed (Has successful State call)
     *
     * @param string $quoteId
     * @return bool
     */
    public function isQuoteProcessed(string $quoteId): bool;

    /**
     * Checks if the Bold order with the specified ID has been processed.
     *
     * @param OrderInterface $order The ID of the Bold order to check.
     * @return bool True if the Bold order has been processed, false otherwise.
     */
    public function isBoldOrderProcessed(OrderInterface $order): bool;

    /**
     * Saves the relationship between a public order ID and a quote ID.
     *
     * @param string $publicOrderId The public order ID to associate with the quote.
     * @param string $quoteId The quote ID to associate with the public order.
     * @return void
     */
    public function saveBoldQuotePublicOrderRelation(string $publicOrderId, string $quoteId): void;

    /**
     * Saves the authorization timestamp for the specified quote.
     *
     * @param string $quoteId The identifier of the quote to associate with the authorization timestamp.
     * @return void
     */
    public function saveAuthorizedAt(string $quoteId): void;

    /**
     * Saves the hydrated timestamp for the specified quote ID.
     *
     * @param string $quoteId The identifier of the quote to update with the hydrated timestamp.
     * @return void
     */
    public function saveHydratedAt(string $quoteId): void;

    /**
     * Saves the current state of a given quote.
     *
     * @param string $quoteId The unique identifier of the quote whose state needs to be saved.
     * @return void
     */
    public function saveStateAt(string $quoteId): void;
}
