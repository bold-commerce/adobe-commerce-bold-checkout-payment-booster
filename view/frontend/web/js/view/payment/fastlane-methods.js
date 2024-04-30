define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (
    Component,
    rendererList
) {
    'use strict';

    if (window.checkoutConfig.bold_fastlane !== undefined) {
        rendererList.push(
            {
                type: 'bold_fastlane',
                component: 'Bold_CheckoutPaymentBooster/js/view/payment/method-renderer/bold-fastlane-method'
            }
        );
    }

    return Component.extend({});
});
