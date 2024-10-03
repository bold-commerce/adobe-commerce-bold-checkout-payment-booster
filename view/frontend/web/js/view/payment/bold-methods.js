define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (
    Component,
    rendererList
) {
    'use strict';

    const shouldShowPaymentMethod = function () {
        return window.checkoutConfig.bold !== undefined
            && window.checkoutConfig.bold.fastlane === undefined;
    }

    if (shouldShowPaymentMethod()) {
        rendererList.push(
            {
                type: 'bold',
                component: 'Bold_CheckoutPaymentBooster/js/view/payment/method-renderer/bold-method'
            }
        );
    }

    return Component.extend({});
});
