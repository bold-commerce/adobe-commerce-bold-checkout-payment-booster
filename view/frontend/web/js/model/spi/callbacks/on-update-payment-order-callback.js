define(
    [
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-address-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-shipping-method-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-wallet-pay-order-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/get-required-order-data-action',
    ],
    function (
        updateQuoteAddressAction,
        updateQuoteShippingMethodAction,
        updateWalletPayOrderAction,
        getRequiredOrderDataAction
    ) {
        'use strict';

        /**
         * Callback function for updating payment order.
         *
         * @param {String} paymentType
         * @param {Object} paymentOrder
         */
        return async function (paymentType, paymentPayload) {
            const paymentData = paymentPayload['payment_data'];
            if (paymentData['shipping_address']) {
                updateQuoteAddressAction('shipping', paymentData['shipping_address']);
            }
            updateQuoteShippingMethodAction(paymentData['shipping_options']);
            if (paymentType === 'ppcp') {
                await updateWalletPayOrderAction(paymentData['order_id']);
            }
            return getRequiredOrderDataAction(
                paymentPayload['require_order_data'] || []
            );
        };
    }
);
