define(
    [
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/address-converter',
    ],
    function (
        quote,
        customerData,
        magentoAddressConverter
    ) {
        'use strict';

        /**
         * Update quote billing or shipping address action.
         *
         * @param {String} addressType
         * @param {Object} addressData
         */
        return function (addressType, addressData) {
            const directoryData = customerData.get('directory-data');
            let regions;
            const countryCode = addressData['country_code'] || addressData['countryCode'];
            try {
                regions = directoryData()[countryCode].regions;
            } catch (e) {
                regions = null;
            }

            let regionId = null;
            let regionName = null;
            const state = addressData['state'] || addressData['administrativeArea'];
            if (regions !== null) {
                Object.entries(regions).forEach(([key, value]) => {
                    if (value.code === state) {
                        regionId = key;
                        regionName = value.name;
                    }
                });
            }
            let firstName = addressData['first_name'] ?? null;
            let lastName = addressData['last_name'] ?? null;
            if (!firstName && !lastName) {
                const nameParts = (addressData['name'] || '').split(' ');
                if (nameParts.length > 1) {
                    firstName = nameParts[0];
                    lastName = nameParts.slice(1).join(' ');
                }
            }
            const street1 = addressData['address1'] || addressData['address_line1'];
            const street2 = addressData['address2'] || addressData['address_line2'];
            const quoteAddress = magentoAddressConverter.formAddressDataToQuoteAddress(
                {
                    address_type: addressType,
                    firstname: firstName,
                    lastname: lastName,
                    street: [
                        street1 || null,
                        street2 || null,
                    ],
                    city: addressData['city'] || addressData['locality'],
                    region: {
                        region: regionName,
                        region_code: state,
                        region_id: regionId
                    },
                    region_id: regionId,
                    telephone: addressData['phoneNumber'] ?? null,
                    postcode: addressData['postal_code'] || addressData['postalCode'],
                    country_id: countryCode,
                    email: addressData['email'] ?? null
                }
            );

            if (addressType === 'shipping') {
                quote.shippingAddress(quoteAddress);
                return;
            }
            quote.billingAddress(quoteAddress);
        }
    }
);
