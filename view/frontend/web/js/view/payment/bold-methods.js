define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (
    Component,
    rendererList
) {
    'use strict';

    let shouldShowPaymentMethod = function () {
        if (window.checkoutConfig.bold !== undefined) {
            if (window.checkoutConfig.bold.fastlane !== undefined) {
                return window.checkoutConfig.bold.alternativePaymentMethods.some(function (method) {
                    return method.type === 'braintree-paypal' || method.type === 'paypal_commerce_platform';
                });
            }
            return true;
        }

        return false;
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
