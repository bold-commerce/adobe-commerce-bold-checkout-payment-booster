<?php

namespace Bold\CheckoutPaymentBooster\Block\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class FlowsIframe extends Field
{
    protected function _getElementHtml(AbstractElement $element)
    {
        $iframeUrl = 'https://example.com';

        return '<iframe src="' . $iframeUrl . '" 
                        width="100%" 
                        height="500px" 
                        frameborder="0" 
                        scrolling="auto">
                </iframe>';
    }
}
