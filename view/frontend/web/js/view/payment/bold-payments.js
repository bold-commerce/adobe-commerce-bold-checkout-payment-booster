define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (
    Component,
    rendererList
) {
    'use strict';
    rendererList.push(
        {
            type: 'bold',
            component: 'Bold_CheckoutPaymentBooster/js/view/payment/method-renderer/bold-spi'
        }
    );
    if (window.checkoutConfig.bold?.fastlane !== undefined) {
        rendererList.push(
            {
                type: 'bold_fastlane',
                component: 'Bold_CheckoutPaymentBooster/js/view/payment/method-renderer/bold-fastlane'
            }
        );
    }
    return Component.extend({});
});
