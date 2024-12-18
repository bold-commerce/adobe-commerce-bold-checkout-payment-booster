define(
    [
        'Magento_Checkout/js/model/quote',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-address-action',
    ],
    function (
        quote,
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
            const shippingAddress = paymentData['shipping_address'];
            const billingAddress = paymentData['billing_address'];

            let email;
            if (shippingAddress && shippingAddress['emailAddress']) { // Apple Pay
                email = shippingAddress['emailAddress'];
            } else if (paymentData['email']) { // Braintree Google Pay
                email = paymentData['email'];
            } else if (paymentData['customer'] && paymentData['customer']['email_address']) { // PPCP Google Pay
                email = paymentData['customer']['email_address'];
            }

            if (email) {
                if (shippingAddress) {
                    shippingAddress.email = email;
                }

                billingAddress.email = email;
                quote.guestEmail = email;
            }

            if (shippingAddress) {
                updateQuoteAddressAction('shipping', shippingAddress);
            }
            updateQuoteAddressAction('billing', billingAddress);
        };
    }
);
