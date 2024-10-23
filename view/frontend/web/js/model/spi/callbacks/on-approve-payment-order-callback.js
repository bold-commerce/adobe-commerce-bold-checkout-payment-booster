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
         */
        return async function (paymentType, paymentPayload) {
            return placeOrderAction(paymentType, paymentPayload);
        };
    }
);
