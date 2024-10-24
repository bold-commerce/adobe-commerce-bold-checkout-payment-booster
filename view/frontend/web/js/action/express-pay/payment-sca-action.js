define(
    [
        'Bold_CheckoutPaymentBooster/js/model/bold-frontend-client',
    ],
    function (
        boldFrontendClient,
    ) {
        'use strict';

        /**
         * Call payment/on_sca endpoint to get the SCA data.
         */
        return async function (paymentPayload) {
            return await boldFrontendClient.post(
                'payments/on_sca',
                paymentPayload
            );
        };
    });
