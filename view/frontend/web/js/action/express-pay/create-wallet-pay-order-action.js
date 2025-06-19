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
        return function (paymentPayload) {
            return platformClient.post(
                'rest/V1/express_pay/order/create',
                {
                    quoteMaskId: window.checkoutConfig.quoteData.entity_id,
                    publicOrderId: window.checkoutConfig.bold.publicOrderId,
                    gatewayId: paymentPayload.gateway_id,
                    shippingStrategy: paymentPayload.shipping_strategy || 'dynamic',
                    shouldVault: paymentPayload.should_vault  || false
                }
            );
        };
    });
