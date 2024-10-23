define(
    [
        'Bold_CheckoutPaymentBooster/js/action/express-pay/create-wallet-pay-order-action'
    ],
    function (
        createWalletPayOrderAction
    ) {
        'use strict';

        /**
         * Callback function for creating wallet pay order.
         *
         * @param {String} paymentType
         * @param {Object} paymentOrder
         */
        return async function (paymentType, paymentPayload) {
            if (paymentType !== 'ppcp') {
                return;
            }
            const walletPayResult = await createWalletPayOrderAction(paymentPayload);
            return {
                payment_data: {
                    id: walletPayResult[0]
                }
            };
        };
    }
);
