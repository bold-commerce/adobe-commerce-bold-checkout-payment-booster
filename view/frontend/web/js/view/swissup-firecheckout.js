define([
    'Bold_CheckoutPaymentBooster/js/action/general/update-payment-methods',
], function (updatePaymentMethods) {
    'use strict';

    var checkoutConfig = window.checkoutConfig;

    return function (target) {
        if (!checkoutConfig || checkoutConfig.bold.thirdPartyCheckout !== 'Swissup') {
            return target;
        }

        return target.extend({
            applyShippingMethod: function (force) {
                this._super(force);
                if (!jQuery('input[value^="bold"]:visible').length) {
                    updatePaymentMethods();
                }
            }
        });
    };
});
