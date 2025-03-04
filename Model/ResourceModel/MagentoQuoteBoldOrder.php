<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class MagentoQuoteBoldOrder extends AbstractDb
{
    /**
     * @var array{array{field: string, title: string}}
     */
    protected $_uniqueFields = [
        [
            'field' => 'quote_id',
            'title' => 'Magento quote identifier'
        ]
    ];

    /**
     * @noinspection MagicMethodsValidityInspection
     */
    protected function _construct(): void
    {
        $this->_init('bold_booster_magento_quote_bold_order', 'id');
    }
}
