<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\_Stubs\Magento\Quote\Model\ResourceModel\Quote;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote\Collection;

class CollectionStub extends Collection
{
    /**
     * @param Quote[] $items
     */
    public function setItems(array $items): CollectionStub
    {
        $this->_items = $items;

        return $this;
    }

    public function getItems(): array
    {
        return $this->_items;
    }

    public function load($printQuery = false, $logQuery = false): CollectionStub
    {
        return $this;
    }
}
