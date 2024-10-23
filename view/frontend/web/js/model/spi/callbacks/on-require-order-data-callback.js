define(
    [
        'Bold_CheckoutPaymentBooster/js/action/express-pay/get-required-order-data-action'
    ],
    function (
        getRequiredOrderDataAction
    ) {
        'use strict';

        /**
         * Callback function for creating wallet pay order.
         *
         * @param {String} paymentType
         * @param {Object} paymentOrder
         */
        return function (requirements) {
            return getRequiredOrderDataAction(requirements);
        };
    }
);
