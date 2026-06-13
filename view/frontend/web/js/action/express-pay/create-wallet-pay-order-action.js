define(
    [
        'Bold_CheckoutPaymentBooster/js/model/platform-client'
    ],
    function (
        platformClient
    ) {
        'use strict';

        /**
         * Create Wallet Pay order — ties the active Magento quote to the Bold public_order_id.
         *
         * Called when the shopper approves PayPal / Google Pay on checkout or PDP.
         * publicOrderId must be renewed after a prior wallet order completes (session-reuse fix).
         *
         * @param {Object} paymentPayload
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
                    shouldVault: paymentPayload.should_vault || false,
                    paymentSource: paymentPayload.payment_data?.payment_source || '',
                }
            );
        };
    });
