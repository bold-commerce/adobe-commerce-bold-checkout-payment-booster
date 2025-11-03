<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\ResourceModel\Order;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Bold order data resource model.
 */
class OrderExtensionData extends AbstractDb
{
    public const TABLE = 'bold_checkout_payment_booster_order';
    public const ID = 'id';
    public const ORDER_ID = 'order_id';
    public const PUBLIC_ID = 'public_id';
    public const IS_CAPTURE_IN_PROGRESS = 'is_capture_in_progress';
    public const IS_REFUND_IN_PROGRESS = 'is_refund_in_progress';
    public const IS_CANCEL_IN_PROGRESS = 'is_cancel_in_progress';
    public const IS_BOLD_INTEGRATION_CART = 'is_bold_integration_cart';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE, self::ID);
    }
}
