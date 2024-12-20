define(
    [
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-address-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-shipping-method-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/save-shipping-information-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/create-wallet-pay-order-action'
    ],
    function (
        updateQuoteAddressAction,
        updateQuoteShippingMethodAction,
        saveShippingInformationAction,
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
            const paymentData = paymentPayload['payment_data'];
            const availableWalletTypes = ['apple', 'google'];
            const isWalletPayment = availableWalletTypes.includes(paymentData.payment_type);

            if (paymentType !== 'ppcp') {
                return;
            }

            if (isWalletPayment) {
                if (paymentData['shipping_address']) {
                    updateQuoteAddressAction('shipping', paymentData['shipping_address']);
                }
                if (paymentData['billing_address']) {
                    updateQuoteAddressAction('billing', paymentData['billing_address']);
                }
            } else {
                await updateQuoteShippingMethodAction(paymentData['shipping_options']);
            }

            await saveShippingInformationAction(true);

            const walletPayResult = await createWalletPayOrderAction(paymentPayload);
            return {
                payment_data: {
                    id: walletPayResult[0]
                }
            };
        };
    }
);
