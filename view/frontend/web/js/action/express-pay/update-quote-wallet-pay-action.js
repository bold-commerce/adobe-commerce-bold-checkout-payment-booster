define(
    [
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-address-action',
    ],
    function (
        updateQuoteAddressAction,
    ) {
        'use strict';

        /**
         * Place order action.
         *
         * @return {Promise}
         */
        return async function (paymentApprovalData) {
            const paymentData = paymentApprovalData['payment_data'];
            const customer = paymentData['customer'];
            const _convertAddress = function (address, customer) {
                address.email = customer.email_address;

                return address;
            }

            updateQuoteAddressAction('shipping', _convertAddress(paymentData.shipping_address, customer));
            updateQuoteAddressAction('billing', _convertAddress(paymentData.billing_address, customer));
        };
    }
);
