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
         * @param {String}  addressType
         * @param {Object}  addressData
         * @param {Object}  [options]
         * @param {boolean} [options.skipRates=false] Skip triggering newAddressProcessor.getRates().
         *   Pass true when the caller will fetch rates via a different path (e.g. DW quote PDP flow)
         *   to prevent a concurrent incorrect request to carts/mine overwriting shippingService.
         */
        return function (addressType, addressData, options = {}) {
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
            const state = addressData['state']
                || addressData['administrativeArea']
                || addressData['adminArea1'];
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
            if (!firstName) {
                firstName = window.checkoutConfig?.boldExpressPayCustomer?.firstname || 'Guest';
            }
            if (!lastName) {
                lastName = window.checkoutConfig?.boldExpressPayCustomer?.lastname || 'Customer';
            }
            let street1 = addressData['address1'] || addressData['address_line1'] || addressData['line1'];
            let street2 = addressData['address2'] || addressData['address_line2'] || addressData['line2'];
            if (addressData['addressLines']) {
                street1 = addressData['addressLines'][0] || street1;
                street2 = addressData['addressLines'][1] || street2;
            }
            if (!street1) {
                street1 = 'N/A';
            }
            const region = regionId ? {
                region: regionName,
                region_code: state,
                region_id: regionId
            } : regionName;
            const email = addressData['email']
                || addressData['emailAddress']
                || quote.guestEmail
                || quote.shippingAddress()?.email
                || quote.billingAddress()?.email
                || window.checkoutConfig?.boldExpressPayCustomer?.email;
            const phone = addressData['phone']
                || addressData['telephone']
                || addressData['phoneNumber']
                || quote.shippingAddress()?.telephone
                || quote.billingAddress()?.telephone
                || '0000000000';
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

            // Trigger rate loading only when the caller has not opted out.
            // In the DW PDP flow (onUpdatePaymentOrder), skipRates=true because the caller
            // fetches rates directly via the DW guest-cart endpoint to avoid a concurrent
            // incorrect request to carts/mine (which always resolves for logged-in customers)
            // overwriting shippingService before our correct rates arrive.
            if (!options.skipRates && quote.shippingAddress()) {
                newAddressProcessor.getRates(quote.shippingAddress());
                shippingService.getShippingRates().subscribe(function (rates) {
                    cartCache.set('rates', rates);
                    let shippingAddress = _.pick(quote.shippingAddress(), cartCache.requiredFields);

                    cartCache.set('shipping-address', shippingAddress);
                });
            }
        }
    }
);
