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
}
