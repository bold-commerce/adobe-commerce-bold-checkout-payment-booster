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
         * @param {Object} paymentPayload
         * @return {Promise}
         */
        return async function (paymentPayload) {
            // todo: should be put instead of post method.
            return platformClient.post(
                'rest/V1/express_pay/order/update',
                {
                    quoteMaskId: window.checkoutConfig.quoteData.entity_id,
                    gatewayId: paymentPayload.gateway_id,
                    paypalOrderId: paymentPayload.payment_data.order_id,
                }
            );
        };
    });
