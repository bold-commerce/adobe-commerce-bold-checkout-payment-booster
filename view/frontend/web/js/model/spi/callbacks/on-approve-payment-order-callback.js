define(
    [
        'Bold_CheckoutPaymentBooster/js/action/express-pay/place-order-action'
    ],
    function (
        placeOrderAction
    ) {
        'use strict';

        /**
         * Callback function for approving payment order.
         *
         * @param {string} paymentType
         * @param {{}} paymentInformation
         * @param {{}} paymentPayload
         * @return {Promise}
         */
        return async function (paymentType, paymentInformation, paymentPayload) {
            return placeOrderAction(paymentType, paymentInformation, paymentPayload);
        };
    }
);
