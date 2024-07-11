<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Checkout flow management model.
 */
class Flow
{
    private const THREE_PAGE_FLOW = 'Bold three page';

    /**
     * Get checkout flow id for the quote.
     *
     * @param CartInterface $quote
     * @return string
     */
    public function getCheckoutFlowId(CartInterface $quote): string
    {
        return self::THREE_PAGE_FLOW; //todo: check if api should be used instead.
    }
}
