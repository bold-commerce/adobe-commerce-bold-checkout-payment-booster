define(
    [
        'Bold_CheckoutPaymentBooster/js/model/platform-client'
    ],
    function (
        platformClient
    ) {
        'use strict';

        /**
         * Create Wallet Pay order.
         *
         * @param {{}}
         * @return {Promise}
         */
        return function () {
            return platformClient.get('rest/V1/cart/getQuote', {});
        };
    }
);
