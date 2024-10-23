define(
    [
        'uiRegistry',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/redirect-on-success',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/place-order-action/process-ppcp-order-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/place-order-action/process-braintree-order-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/save-shipping-information-action',
    ],
    function (
        registry,
        $,
        quote,
        placeOrderAction,
        redirectOnSuccessAction,
        processPpcpOrderAction,
        processBraintreeOrderAction,
        saveShippingInformationAction
    ) {
        'use strict';

        /**
         * Place express-order action.
         *
         * @return {Promise}
         */
        return async function (paymentType, paymentApprovalData) {
            if (paymentApprovalData === null) {
                console.error('Express Pay payment data is not set.');
                return;
            }
            const paymentMethodData = {
                method: 'bold',
            };
            if (paymentType === 'ppcp') {
                paymentMethodData['additional_data'] = {
                    order_id: paymentApprovalData?.payment_data.order_id
                };
            }
            if (paymentType === 'ppcp') {
                await processPpcpOrderAction(paymentApprovalData);
            } else {
                await processBraintreeOrderAction(paymentApprovalData);
            }
            try {
                await saveShippingInformationAction(true);
            } catch (error) {
                console.error('Could not save shipping information for Express Pay order.', error);
                return;
            }
            const messageContainer = registry.get('checkout.errors').messageContainer;
            $.when(placeOrderAction(paymentMethodData, messageContainer))
                .done(
                    function () {
                        redirectOnSuccessAction.execute();
                    }
                );
        };
    }
);