define(
    [
        'Bold_CheckoutPaymentBooster/js/action/express-pay/payment-sca-action'
    ],
    function (
        paymentScaAction
    ) {
        'use strict';

        /**
         * Callback function for approving payment order.
         *
         */
        return async function (paymentType, paymentPayload) {
            if (paymentType === 'ppcp') {
                const scaResult = await paymentScaAction({
                    'gateway_type': 'ppcp',
                    'order_id': paymentPayload.order_id,
                    'public_order_id': window.checkoutConfig.bold.publicOrderId
                });
                return {card: scaResult};
            }
            throw new Error('Unsupported payment type');
        };
    }
);
