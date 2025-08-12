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

    /**
     * @param int|string $quoteId
     * @return MagentoQuoteBoldOrderInterface
     */
    public function setQuoteId($quoteId): MagentoQuoteBoldOrderInterface;

    /**
     * @return string|int|null
     */
    public function getQuoteId();

    /**
     * @param string $boldOrderId
     * @return MagentoQuoteBoldOrderInterface
     */
    public function setBoldOrderId(string $boldOrderId): MagentoQuoteBoldOrderInterface;

    /**
     * @return string|null
     */
    public function getBoldOrderId(): ?string;

    /**
     * @param string $timestamp
     * @return MagentoQuoteBoldOrderInterface
     */
    public function setSuccessfulHydrateAt(string $timestamp): MagentoQuoteBoldOrderInterface;

    /**
     * @return string|null
     */
    public function getSuccessfulHydrateAt(): ?string;

    /**
     * @param string $timestamp
     * @return MagentoQuoteBoldOrderInterface
     */
    public function setSuccessfulAuthFullAt(string $timestamp): MagentoQuoteBoldOrderInterface;

    /**
     * @return string|null
     */
    public function getSuccessfulAuthFullAt(): ?string;

    /**
     * @param string $timestamp
     * @return MagentoQuoteBoldOrderInterface
     */
    public function setSuccessfulStateAt(string $timestamp): MagentoQuoteBoldOrderInterface;

    /**
     * @return string|null
     */
    public function getSuccessfulStateAt(): ?string;

    /**
     * @return bool
     */
    public function isProcessed(): bool;
}
