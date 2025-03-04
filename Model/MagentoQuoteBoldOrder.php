<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Model;

use Bold\CheckoutPaymentBooster\Api\Data\MagentoQuoteBoldOrderInterface;
use Bold\CheckoutPaymentBooster\Model\ResourceModel\MagentoQuoteBoldOrder as MagentoQuoteBoldOrderResourceModel;
use Magento\Framework\Model\AbstractModel;

class MagentoQuoteBoldOrder extends AbstractModel implements MagentoQuoteBoldOrderInterface
{
    protected $_eventPrefix = 'magento_quote_bold_order_model';

    /**
     * @noinspection MagicMethodsValidityInspection
     */
    public function _construct(): void
    {
        $this->_init(MagentoQuoteBoldOrderResourceModel::class);
    }

    public function setQuoteId($quoteId): MagentoQuoteBoldOrderInterface
    {
        $this->setData(self::QUOTE_ID, $quoteId);

        return $this;
    }

    public function getQuoteId()
    {
        return $this->getData(self::QUOTE_ID);
    }

    public function setBoldOrderId(string $boldOrderId): MagentoQuoteBoldOrderInterface
    {
        $this->setData(self::BOLD_ORDER_ID, $boldOrderId);

        return $this;
    }

    public function getBoldOrderId(): ?string
    {
        return $this->getData(self::BOLD_ORDER_ID);
    }
}
