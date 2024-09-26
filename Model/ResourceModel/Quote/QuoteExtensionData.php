<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\ResourceModel\Quote;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Bold quote data resource model.
 */
class QuoteExtensionData extends AbstractDb
{
    public const TABLE = 'bold_checkout_payment_booster_quote';
    public const ID = 'id';
    public const QUOTE_ID = 'quote_id';
    public const PUBLIC_ID = 'public_id';
    public const FLOW_SETTINGS = 'flow_settings';
    public const ORDER_CREATED = 'order_created';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE, self::ID);
    }
}
