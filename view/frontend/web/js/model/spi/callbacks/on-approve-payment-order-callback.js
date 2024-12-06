define(
    [
        'uiRegistry',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/redirect-on-success',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-ppcp-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-braintree-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/save-shipping-information-action',
        'Magento_Ui/js/model/messageList'
    ],
    function (
        registry,
        $,
        quote,
        placeOrderAction,
        redirectOnSuccessAction,
        updateQuotePPCPAction,
        updateQuoteBraintreeAction,
        saveShippingInformationAction,
        messageList
    ) {
        'use strict';

        /**
         * Place express-order action.
         *
         * @param {string} paymentType
         * @param {{}} paymentInformation
         * @param {{}} paymentApprovalData
         * @return {Promise}
         */
        return async function (paymentType, paymentInformation, paymentApprovalData) {
            const paymentData = paymentApprovalData['payment_data'];
            const availableWalletTypes = ['apple', 'google'];
            const isWalletPayment = availableWalletTypes.includes(paymentData.payment_type);

            if (paymentApprovalData === null) {
                console.error('Express Pay payment data is not set.');
                return;
            }
            const paymentMethodData = {
                method: window.checkoutConfig?.bold?.paymentBooster?.payment?.method ?? 'bold',
            };

            if (paymentType === 'ppcp' || isWalletPayment) {
                paymentMethodData['additional_data'] = {
                    order_id: paymentApprovalData?.payment_data.order_id
                };
            }

            if (paymentType === 'ppcp') {
                await updateQuotePPCPAction(paymentApprovalData);
            } else {
                await updateQuoteBraintreeAction(paymentInformation, paymentApprovalData);
            }

            if (!isWalletPayment) {
                try {
                    await saveShippingInformationAction(true);
                } catch (error) {
                    console.error('Could not save shipping information for Express Pay order.', error);
                    return;
                }
            }

            const messageContainer = registry.get('checkout.errors')?.messageContainer ?? messageList;
            $.when(placeOrderAction(paymentMethodData, messageContainer))
                .done(
                    function () {
                        redirectOnSuccessAction.execute();
                    }
                );
        };
    }
);
