define([
        'Bold_CheckoutPaymentBooster/js/model/platform-client'
    ],
    function (
        platformClient
    ) {
        'use strict';

        /**
         * Create Wallet Pay order.
         *
         * @param {Object} paymentPayload
         * @returns {Promise}
         */
        return async function (paymentPayload) {
            return platformClient.post(
                'rest/V1/express_pay/order/create',
                {
                    quoteMaskId: window.checkoutConfig.quoteData.entity_id,
                    gatewayId: paymentPayload.gateway_id,
                    shippingStrategy: paymentPayload.shipping_strategy //todo: Check if it is a required param as there is no such property in paymentPayload fir the PPCP credit card.
                }
            );
        };
    });
