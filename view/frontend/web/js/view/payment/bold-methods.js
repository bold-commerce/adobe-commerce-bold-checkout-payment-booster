define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (
    Component,
    rendererList
) {
    'use strict';

    if (window.checkoutConfig.bold !== undefined) {
        rendererList.push(
            {
                type: 'bold',
                component: 'Bold_CheckoutPaymentBooster/js/view/payment/method-renderer/bold-method'
            }
        );
    }

    return Component.extend({});
});
