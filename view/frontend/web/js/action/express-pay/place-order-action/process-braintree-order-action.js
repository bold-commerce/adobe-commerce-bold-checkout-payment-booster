define(
    [
        'Magento_Checkout/js/model/quote',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-address-action',
    ],
    function (
        quote,
        updateQuoteAddressAction
    ) {
        'use strict';

        /**
         * Process braintree order action.
         *
         * @param {{}} paymentInformation
         * @param {{}} paymentApprovalData
         * @return {void}
         */
        return async function (paymentInformation, paymentApprovalData) {
            if (paymentApprovalData.payment_data.email) {
                quote.guestEmail = paymentApprovalData.payment_data.email;
                paymentApprovalData.payment_data.shipping_address['email'] = paymentApprovalData.payment_data.email;
                paymentApprovalData.payment_data.billing_address['email'] = paymentApprovalData.payment_data.email;
            }
            if (paymentApprovalData.payment_data.shipping_address) {
                updateQuoteAddressAction('shipping', paymentApprovalData.payment_data.shipping_address);
            }
            if (paymentApprovalData.payment_data.billing_address) {
                updateQuoteAddressAction('billing', paymentApprovalData.payment_data.billing_address);
            }
        };
    }
);
