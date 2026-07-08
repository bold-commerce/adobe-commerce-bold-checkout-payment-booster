define(
    [
        'Bold_CheckoutPaymentBooster/js/model/platform-client'
    ],
    function (
        platformClient
    ) {
        'use strict';

        /**
         * @returns {string|null}
         */
        function resolvePublicOrderId() {
            const fromQuote = window.checkoutConfig?.quoteData?.extension_attributes?.bold_order_id;

            if (fromQuote) {
                return fromQuote;
            }

            return window.checkoutConfig?.bold?.publicOrderId ?? null;
        }

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
                    publicOrderId: resolvePublicOrderId(),
                    gatewayId: paymentPayload.gateway_id,
                    shippingStrategy: paymentPayload.shipping_strategy || 'dynamic',
                    shouldVault: paymentPayload.should_vault || false,
                    paymentSource: paymentPayload.payment_data?.payment_source || '',
                }
            );
        };
    });
