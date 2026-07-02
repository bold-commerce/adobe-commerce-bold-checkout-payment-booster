define(
    [
        'uiRegistry',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/redirect-on-success',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-wallet-pay-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-ppcp-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-braintree-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-stripe-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-shipping-method-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-wallet-pay-order-action',
        'Magento_Ui/js/model/messageList'
    ],
    function (
        registry,
        $,
        quote,
        placeOrderAction,
        redirectOnSuccessAction,
        updateQuoteWalletPayAction,
        updateQuotePPCPAction,
        updateQuoteBraintreeAction,
        updateQuoteStripeAction,
        updateQuoteShippingMethodAction,
        updateWalletPayOrderAction,
        messageList
    ) {
        'use strict';

        /**
         * Resolve PayPal / Bold shipping options from approval payload.
         *
         * @param {Object} paymentApprovalData
         * @returns {Array|Object|null}
         */
        function resolveShippingOptions(paymentApprovalData) {
            const paymentData = paymentApprovalData?.payment_data;

            return paymentData?.shipping_options
                || paymentApprovalData?.shipping_options
                || null;
        }

        /**
         * Place express-order action.
         *
         * @param {string} paymentType
         * @param {{}} paymentInformation
         * @param {{}} paymentApprovalData
         * @return {Promise}
         */
        return async function (paymentType, paymentInformation, paymentApprovalData) {
            if (paymentApprovalData === null) {
                console.error('Express Pay payment data is not set.');
                return;
            }

            const paymentData = paymentApprovalData['payment_data'];
            const availableWalletTypes = ['apple', 'google'];
            const isWalletPayment = availableWalletTypes.includes(paymentData.payment_type);
            const isSpiContainer = paymentApprovalData.containerId === 'SPI' ||  paymentApprovalData.containerId === 'wallet-payments';
            const shippingStrategy = isSpiContainer ? 'fixed' : (paymentApprovalData?.shipping_strategy ?? 'dynamic');

            const paymentMethodData = {
                method: window.checkoutConfig?.bold?.paymentBooster?.payment?.method ?? 'bold',
            };

            if (paymentType === 'ppcp' || isWalletPayment) {
                paymentMethodData['additional_data'] = {
                    order_id: paymentApprovalData?.payment_data.order_id ?? paymentApprovalData.order_id
                };
            }

            if (isWalletPayment) {
                await updateQuoteWalletPayAction(paymentApprovalData, isSpiContainer);
            } else if (paymentType === 'ppcp') {
                await updateQuotePPCPAction(paymentApprovalData, isSpiContainer);
            } else if (paymentType === 'stripe') {
                await updateQuoteStripeAction(paymentInformation, paymentApprovalData, isSpiContainer);
            } else {
                await updateQuoteBraintreeAction(paymentInformation, paymentApprovalData, isSpiContainer);
            }

            if (!isSpiContainer) {
                try {
                    await updateQuoteShippingMethodAction(
                        resolveShippingOptions(paymentApprovalData),
                        { save: true, saveBillingAddress: true }
                    );
                } catch (error) {
                    console.error('Could not save shipping information for Express Pay order.', error);
                    return;
                }
            }

            if (paymentType === 'ppcp' && !isWalletPayment) {
                const orderId = paymentApprovalData?.payment_data?.order_id ?? paymentApprovalData?.order_id;
                const gatewayId = paymentApprovalData?.gateway_id;
                if (orderId && gatewayId) {
                    try {
                        await updateWalletPayOrderAction(orderId, gatewayId, shippingStrategy);
                    } catch (error) {
                        console.error('Could not perform final sync of wallet pay order.', error);
                        return;
                    }
                }
            }

            const messageContainer = registry.get('checkout.errors')?.messageContainer ?? messageList;
            $('body').trigger('processStart');
            $.when(placeOrderAction(paymentMethodData, messageContainer))
                .done(
                    function () {
                        redirectOnSuccessAction.execute();
                    }
                ).always(
                    function () {
                        $('body').trigger('processStop');
                    }
                );
        };
    }
);
