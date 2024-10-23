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
         * @return {void}
         */
        return async function (paymentApprovalData) {
            quote.guestEmail = paymentApprovalData.payment_data.email;
            paymentApprovalData.payment_data.shipping_address['email'] = paymentApprovalData.payment_data.email;
            paymentApprovalData.payment_data.billing_address['email'] = paymentApprovalData.payment_data.email;
            updateQuoteAddressAction('shipping', paymentApprovalData.payment_data.shipping_address);
            updateQuoteAddressAction('billing', paymentApprovalData.payment_data.billing_address);
        };
    }
);
