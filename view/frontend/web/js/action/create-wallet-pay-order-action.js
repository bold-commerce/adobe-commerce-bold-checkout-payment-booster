define(
    [
        'Bold_CheckoutPaymentBooster/js/model/bold-frontend-client'
    ],
    function (
        boldClient,
    ) {
        'use strict';

        /**
         * Create Wallet Pay order.
         */
        return async function (paymentPayload) {
            return await boldClient.post(
                'wallet_pay/create_order',
                paymentPayload
            );
        };
    });
