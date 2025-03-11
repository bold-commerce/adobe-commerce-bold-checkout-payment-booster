define(
    [
        'jquery',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-address-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-shipping-method-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-wallet-pay-order-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/get-required-order-data-action',
        'Magento_Checkout/js/action/get-totals'
    ],
    function (
        $,
        updateQuoteAddressAction,
        updateQuoteShippingMethodAction,
        updateWalletPayOrderAction,
        getRequiredOrderDataAction,
        getTotalsAction
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
            const availableWalletTypes = ['apple', 'google'];
            const isWalletPayment = availableWalletTypes.includes(paymentData.payment_type);

            if (paymentData['shipping_address']) {
                updateQuoteAddressAction('shipping', paymentData['shipping_address']);
            }
            await updateQuoteShippingMethodAction(paymentData['shipping_options']);

            if (paymentType === 'ppcp' && !isWalletPayment) {
                await updateWalletPayOrderAction(paymentData['order_id'], paymentPayload['gateway_id']);
            }

            return getRequiredOrderDataAction(
                paymentPayload['require_order_data'] || []
            );
        };
    }
);
