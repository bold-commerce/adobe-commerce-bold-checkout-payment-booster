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
        return async function (paymentInformation, paymentApprovalData, isSpiContainer) {
            const paymentData = paymentApprovalData.payment_data;
            if (paymentData.email) {
                quote.guestEmail = paymentData.email;
                paymentData.shipping_address['email'] = paymentData.email;
                paymentData.billing_address['email'] = paymentData.email;
            }
            if (!quote.guestEmail && paymentData.billing_address.email) {
                quote.guestEmail = paymentData.billing_address.email;
            }
            if (!quote.guestEmail && paymentData.shipping_address.email) {
                quote.guestEmail = paymentData.shipping_address.email;
            }
            if (paymentData.customer) {
                quote.guestEmail = paymentData.customer.email_address;
                if (paymentData.billing_address) {
                    paymentData.billing_address['email'] = paymentData.customer.email_address;
                    paymentData.billing_address['first_name'] = paymentData.customer.first_name;
                    paymentData.billing_address['last_name'] = paymentData.customer.last_name;
                    paymentData.billing_address['phone'] = paymentData.customer.phone;
                }
                if (paymentData.shipping_address) {
                    paymentData.shipping_address['email'] = paymentData.customer.email_address;
                    paymentData.shipping_address['first_name'] = paymentData.customer.first_name;
                    paymentData.shipping_address['last_name'] = paymentData.customer.last_name;
                    paymentData.shipping_address['phone'] = paymentData.customer.phone;
                }
            }

            if (isSpiContainer) {
                if (paymentData.billing_address) {
                    updateQuoteAddressAction('billing', paymentData.billing_address);
                }
            } else {
                if (paymentData.shipping_address) {
                    updateQuoteAddressAction('shipping', paymentData.shipping_address);
                }

                if (paymentData.billing_address) {
                    updateQuoteAddressAction('billing', paymentData.billing_address);
                }
            }
        };
    }
);
