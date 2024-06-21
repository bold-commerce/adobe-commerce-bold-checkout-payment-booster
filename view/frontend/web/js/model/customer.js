define([
    'Magento_Checkout/js/model/quote',
    'checkoutData'
], function (
    quote,
    checkoutData
) {
    'use strict';

    return {
        /**
         * Get customer api data.
         *
         * @return object|null
         */
        getCustomer: function () {
            const billingAddress = checkoutData.getBillingAddressFromData();
            const shippingAddress = checkoutData.getShippingAddressFromData();
            if (!billingAddress && !shippingAddress) {
                return null;
            }
            const firstname = (billingAddress && billingAddress.firstname)
                || (shippingAddress && shippingAddress.firstname)
                || '';
            const lastname = (billingAddress && billingAddress.lastname)
                || (shippingAddress && shippingAddress.lastname)
                || '';
            const payload = {
                'email_address': checkoutData.getValidatedEmailValue(),
                'first_name': firstname,
                'last_name': lastname,
            }
            if (!payload.email_address || !payload.first_name || !payload.last_name) {
                return null;
            }

            return payload;
        }
    };
});
