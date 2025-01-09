<?php

namespace Bold\CheckoutPaymentBooster\Block\System\Config\Payment;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class PortalIframe extends Field
{
    /** @var string */
    protected $_template = 'Bold_CheckoutPaymentBooster::system/config/payment/portal_iframe.phtml';

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
