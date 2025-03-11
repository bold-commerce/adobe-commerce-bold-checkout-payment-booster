define(
    [
        'Bold_CheckoutPaymentBooster/js/action/digital-wallets/create-quote',
        'Bold_CheckoutPaymentBooster/js/action/digital-wallets/get-payment-gateways',
    ],
    function (createQuote, getPaymentGateways) {
        'use strict';

        /**
         * @param {Object} paymentType
         * @param {Object} paymentPayload
         * @returns {Promise<void>}
         * @throws Error
         */
        return async function (paymentType, paymentPayload) {
            let paymentGateways;

            if (!paymentPayload.containerId.includes('product-detail')) {
                return;
            }

            await createQuote();

            if (
                !window.checkoutConfig?.quoteData?.hasOwnProperty('extension_attributes')
                || !window.checkoutConfig?.quoteData?.extension_attributes?.hasOwnProperty('bold_order_id')
            ) {
                return;
            }

            window.boldPaymentsInstance.setTraceId(window.checkoutConfig.quoteData.extension_attributes.bold_order_id);

            paymentGateways = await getPaymentGateways();

            if (paymentGateways.length === 0) {
                return;
            }

            window.boldPaymentsInstance.updateGatewaysAuthToken(
                paymentGateways.map(
                    paymentGateway => ({
                        gateway_id: paymentGateway.id,
                        auth_token: paymentGateway.auth_token,
                        currency: paymentGateway.currency
                    })
                )
            );
        };
    }
);
