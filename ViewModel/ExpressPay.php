<?php

namespace Bold\CheckoutPaymentBooster\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

class ExpressPay implements  ArgumentInterface
{
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    public function __construct(
        StoreManagerInterface $storeManager
    ) {
        $this->storeManager = $storeManager;
    }

    /**
     * Get the current website id.
     *
     * @return int
     */
    public function getWebsiteId(): int
    {
        return (int) $this->storeManager->getStore()->getWebsiteId();
    }
}
