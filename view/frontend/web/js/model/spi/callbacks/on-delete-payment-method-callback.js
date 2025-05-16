define(
    [
        'Bold_CheckoutPaymentBooster/js/model/bold-frontend-client',
    ],
    function (
        boldFrontendClient
    ) {
        'use strict';

        /**
         * Callback function for deleting payment method
         */
        return async function (payload) {
            try {
                await boldFrontendClient.delete('payments/saved/' + payload.public_id);
                return payload;
            } catch (e) {
                throw new Error(`Encountered error when attempting to delete saved payment method.`);
            }
        };
    }
);
