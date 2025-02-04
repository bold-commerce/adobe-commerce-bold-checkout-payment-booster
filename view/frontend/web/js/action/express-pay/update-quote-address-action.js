define(
    [
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/address-converter',
        'Magento_Checkout/js/model/shipping-rate-processor/new-address',
        'Magento_Checkout/js/model/cart/cache',
        'Magento_Checkout/js/model/shipping-service'
    ],
    function (
        quote,
        customerData,
        magentoAddressConverter,
        newAddressProcessor,
        cartCache,
        shippingService
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
            if (regions) {
                Object.entries(regions).forEach(([key, value]) => {
                    if (value.code === state || value.name === state) {
                        regionId = key;
                        regionName = value.name;
                    }
                });
            }
            let firstName = addressData['first_name'] || addressData['givenName'] || null;
            let lastName = addressData['last_name'] || addressData['familyName'] || null;
            if (!firstName && !lastName) {
                const nameParts = (addressData['name'] || '').split(' ');
                if (nameParts.length > 1) {
                    firstName = nameParts[0];
                    lastName = nameParts.slice(1).join(' ');
                }
            }
            let street1 = addressData['address1'] || addressData['address_line1'] || addressData['line1'];
            let street2 = addressData['address2'] || addressData['address_line2'] || addressData['line2'];
            if (addressData['addressLines']) {
                street1 = addressData['addressLines'][0] || street1;
                street2 = addressData['addressLines'][1] || street2;
            }
            const region = regionId ? {
                region: regionName,
                region_code: state,
                region_id: regionId
            } : regionName;
            const email = addressData['email'] || quote.shippingAddress.email || quote.billingAddress.email;
            const phone = addressData['phone']
                || addressData['telephone']
                || addressData['phoneNumber']
                || quote.shippingAddress.telephone
                || quote.billingAddress.telephone;
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
                    region: region,
                    region_id: regionId,
                    telephone: phone ?? null,
                    postcode: addressData['postal_code'] || addressData['postalCode'],
                    country_id: countryCode,
                    email: email ?? null
                }
            );

            if (addressType === 'shipping') {
                quote.shippingAddress(quoteAddress);
            } else {
                quote.billingAddress(quoteAddress);
            }

            newAddressProcessor.getRates(quote.shippingAddress());
            shippingService.getShippingRates().subscribe(function (rates) {
                cartCache.set('rates', rates);
                let shippingAddress = _.pick(quote.shippingAddress(), cartCache.requiredFields);

                cartCache.set('shipping-address', shippingAddress);
            });
        }
    }
);
