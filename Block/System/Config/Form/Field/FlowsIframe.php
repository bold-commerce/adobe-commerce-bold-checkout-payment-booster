<?php

namespace Bold\CheckoutPaymentBooster\Block\System\Config\Form\Field;

use Magento\Config\Block\System\Config\Form\Field;

class FlowsIframe extends Field
{
    protected function _getElementHtml()
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
