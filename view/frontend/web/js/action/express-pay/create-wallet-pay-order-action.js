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
         * @param {{}} paymentPayload
         * @return {Promise<Array>}
         */
        return async function (paymentPayload) {
            try {
                const result = await platformClient.post(
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

                if (!Array.isArray(result) || !result.length) {
                    throw new Error('Could not create Express Pay order. Empty response from server.');
                }

                return result;
            } catch (response) {
                const message = response?.responseJSON?.message
                    || response?.statusText
                    || 'Could not create Express Pay order.';
                throw new Error(message);
            }
        };
    }
);
