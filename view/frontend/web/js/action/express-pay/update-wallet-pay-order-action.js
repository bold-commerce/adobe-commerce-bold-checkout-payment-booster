define(
    [
        'Bold_CheckoutPaymentBooster/js/model/platform-client'
    ],
    function (
        platformClient
    ) {
        'use strict';

        /**
         * Update Wallet Pay order.
         *
         * @param {string} orderId
         * @return {Promise}
         */
        return async function (orderId) {
            // todo: should be put instead of post method.
            return platformClient.post(
                'rest/V1/express_pay/order/update',
                {
                    quoteMaskId: window.checkoutConfig.quoteData.entity_id,
                    gatewayId: window.checkoutConfig.bold.gatewayId,
                    paypalOrderId: orderId
                }
            );
        };
    });
