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
         * @param {string} orderId
         * @param {number} paymentGatewayId
         * @return {Promise}
         */
        return async function (orderId, paymentGatewayId) {
            // todo: should be put instead of post method.
            return platformClient.post(
                'rest/V1/express_pay/order/update',
                {
                    quoteMaskId: window.checkoutConfig.quoteData.entity_id,
                    gatewayId: paymentGatewayId,
                    paypalOrderId: orderId,
                }
            );
        };
    });
