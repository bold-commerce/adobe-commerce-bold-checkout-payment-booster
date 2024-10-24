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
        return async function (paymentPayload) {
            return platformClient.post(
                'rest/V1/express_pay/order/create',
                {
                    quoteMaskId: window.checkoutConfig.quoteData.entity_id,
                    gatewayId: paymentPayload.gateway_id,
                    shippingStrategy: paymentPayload.shipping_strategy || 'dynamic'
                }
            );
        };
    });
