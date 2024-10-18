define(
    [
        'Magento_Checkout/js/model/new-customer-address',
        'Magento_Customer/js/customer-data',
        'checkoutData'
    ], function (
        NewCustomerAddressModel,
        customerData,
        checkoutData
    ) {
        'use strict';
        /**
         * Convert Magento address to Bold address.
         *
         * @param {Object} boldAddress
         * @return {Object}
         */
        return function (magentoAddress) {
            let street1 = '';
            let street2 = '';
            if (magentoAddress.street && magentoAddress.street[0]) {
                street1 = magentoAddress.street[0];
            }
            if (magentoAddress.street && magentoAddress.street[1]) {
                street2 = magentoAddress.street[1];
            }

            return {
                email: customerData.email ?? checkoutData.getValidatedEmailValue(),
                country_code: magentoAddress.countryId,
                city: magentoAddress.city ?? '',
                first_name: magentoAddress.firstname ?? '',
                last_name: magentoAddress.lastname ?? '',
                phone_number: magentoAddress.telephone ?? '',
                postal_code: magentoAddress.postcode,
                province: magentoAddress.region,
                province_code: magentoAddress.regionCode,
                address_line_1: street1,
                address_line_2: street2
            };
        };
    });
