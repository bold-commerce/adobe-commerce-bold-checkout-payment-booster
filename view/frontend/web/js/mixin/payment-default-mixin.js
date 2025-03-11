define(['Magento_Checkout/js/model/quote'], function (quote) {
    'use strict';

    return function (originalComponent) {
        return originalComponent.extend({
            /**
             * @returns {boolean}
             */
            isPlaceOrderActionAllowed: function () {
                const method = quote.paymentMethod()
                    ? quote.paymentMethod().method
                    : null;

                if (method === 'bold_wallet') {
                    return false;
                }

                return this._super();
            },
        });
    };
});
