<?php

namespace Bold\CheckoutPaymentBooster\Block\System\Config\Payment;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class PaymentIframe extends Field
{
    /** @var string */
    protected $_template = 'Bold_CheckoutPaymentBooster::system/config/payment/payment_iframe.phtml';

    /**
     * Render element HTML
     *
     * @param  AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        return $this->toHtml();
    }
}
