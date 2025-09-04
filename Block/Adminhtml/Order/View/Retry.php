<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Block\Adminhtml\Order\View;

use Bold\CheckoutPaymentBooster\Model\Config;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;

class Retry extends \Magento\Backend\Block\Template
{
    /** @var Registry  */
    private $registry;

    /**
     * Retry button block
     *
     * @param Context $context
     * @param Registry $registry
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        array $data = []
    ) {
        $this->registry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * Check if is to show retry button - only for bold order
     *
     * @return bool
     */
    public function isShowButton()
    {
        $order = $this->getOrder();
        $method = $order->getPayment()->getMethod();

        return in_array($method, Config::BOLD_PAYMENT_METHODS_CODE, true);
    }

    /**
     * Get order
     *
     * @return mixed|null
     */
    public function getOrder()
    {
        return $this->registry->registry('current_order');
    }

    /**
     * Get retry url
     *
     * @return string
     */
    public function getRetryUrl()
    {
        return $this->getUrl('bold_booster/order/retry', ['order_id' => $this->getOrder()->getId()]);
    }
}
