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
          let payload = {};
          let address = checkoutData.getBillingAddressFromData() || checkoutData.getShippingAddressFromData();
            if (!address) {
                address = quote.billingAddress();
                if (!address) {
                    return null;
                }
            }
          const firstname = address.firstname || '';
          const lastname = address.lastname || '';
          payload = {
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
