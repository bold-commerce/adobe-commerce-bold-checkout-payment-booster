<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Api\Data;

/**
 * @method int|string|null getId()
 * @method MagentoQuoteBoldOrderInterface setId(int|string $id)
 */
interface MagentoQuoteBoldOrderInterface
{
    public const QUOTE_ID = 'quote_id';
    public const BOLD_ORDER_ID = 'bold_order_id';
    public const SUCCESSFUL_HYDRATE_AT = 'successful_hydrate_at';
    public const SUCCESSFUL_AUTH_FULL_AT = 'successful_auth_full_at';
    public const SUCCESSFUL_STATE_AT = 'successful_state_at';
    public const IS_BOLD_INTEGRATION_CART = 'is_bold_integration_cart';

    /**
     * Sets the unique identifier for the quote.
     *
     * @param mixed $quoteId The unique identifier of the quote to be set.
     * @return MagentoQuoteBoldOrderInterface Returns the instance of the class implementing this interface.
     */
    public function setQuoteId($quoteId): MagentoQuoteBoldOrderInterface;

    /**
     * Retrieves the ID associated with the quote.
     *
     * @return mixed
     */
    public function getQuoteId();

    /**
     * Sets the Bold order ID.
     *
     * @param string $boldOrderId The Bold order ID to set.
     * @return MagentoQuoteBoldOrderInterface
     */
    public function setBoldOrderId(string $boldOrderId): MagentoQuoteBoldOrderInterface;

    /**
     * Retrieves the Bold Order ID associated with the current operation or context.
     *
     * @return string|null Returns the Bold Order ID if available, otherwise null.
     */
    public function getBoldOrderId(): ?string;

    /**
     * Sets the timestamp indicating when the hydration process was successfully completed.
     *
     * @param string $timestamp The timestamp of successful hydration.
     * @return MagentoQuoteBoldOrderInterface
     */
    public function setSuccessfulHydrateAt(string $timestamp): MagentoQuoteBoldOrderInterface;

    /**
     * Retrieves the timestamp indicating when the hydration process was successfully completed.
     *
     * @return string|null Returns the timestamp as a string if the hydration was successful,
     * or null if it has not been completed.
     */
    public function getSuccessfulHydrateAt(): ?string;

    /**
     * Sets the timestamp of a successful full authentication.
     *
     * @param string $timestamp The timestamp of the successful authentication in string format.
     * @return MagentoQuoteBoldOrderInterface
     */
    public function setSuccessfulAuthFullAt(string $timestamp): MagentoQuoteBoldOrderInterface;

    /**
     * Retrieves the timestamp of the most recent successful full authentication.
     *
     * @return string|null Returns the timestamp as a string if a successful full authentication exists,
     * or null if not available.
     */
    public function getSuccessfulAuthFullAt(): ?string;

    /**
     * Sets the successful state with the provided timestamp.
     *
     * @param string $timestamp The timestamp at which the successful state is set.
     * @return MagentoQuoteBoldOrderInterface
     */
    public function setSuccessfulStateAt(string $timestamp): MagentoQuoteBoldOrderInterface;

    /**
     * Retrieves the timestamp or identifier indicating when the state was successfully achieved.
     *
     * @return string|null Returns the successful state timestamp or identifier as a string, or null if not applicable.
     */
    public function getSuccessfulStateAt(): ?string;

    /**
     * Checks whether the process has been completed or is in a processed state.
     *
     * @return bool Returns true if the process is completed or processed, otherwise false.
     */
    public function isProcessed(): bool;

    /**
     * Sets whether this is a Bold integration cart.
     *
     * @param bool $flag Whether this is a Bold integration cart.
     * @return MagentoQuoteBoldOrderInterface
     */
    public function setIsBoldIntegrationCart(bool $flag): MagentoQuoteBoldOrderInterface;

    /**
     * Gets whether this is a Bold integration cart.
     *
     * @return bool Returns true if this is a Bold integration cart, otherwise false.
     */
    public function getIsBoldIntegrationCart(): bool;
}
