define([
    'underscore',
    'Magento_Checkout/js/model/quote',
    'uiRegistry',
], function (
    _,
    quote,
    registry
) {
    'use strict';

    /**
     * Bold address model.
     *
     * @type object
     */
    const boldAddress = {
        initialize: function () {
            this.countries = window.checkoutConfig.bold.countries;
        },

        /**
         * Get billing address api data.
         *
         * @return object
         */
        getBillingAddress: function () {
            this.billingAddress = quote.billingAddress();
            this.shippingAddress = quote.shippingAddress();
            if (!this.billingAddress && !this.shippingAddress) {
                return null;
            }
            const postCode = registry.get('index = postcode')
            if (postCode && postCode.warn()) {
                return null;
            }
            const countryId = this.getFieldValue('countryId');
            const country = this.countries.find(country => country.value === countryId);
            const countryName = country ? country.label : '';
            let street1 = '';
            let street2 = '';
            if (this.billingAddress.street && this.billingAddress.street[0]) {
                street1 = this.billingAddress.street[0];
            }
            if (this.billingAddress.street && this.billingAddress.street[1]) {
                street2 = this.billingAddress.street[1];
            }
            if (!street1) {
                const street1Field = this.billingAddress
                && this.billingAddress.isAddressSameAsShipping
                && !this.billingAddress.isAddressSameAsShipping()
                    ? registry.get('dataScope = billingAddress.street.0')
                    : registry.get('dataScope = shippingAddress.street.0');
                if (street1Field) {
                    street1 = street1Field.value();
                }
            }
            if (!street2) {
                const street2Field = this.billingAddress
                && this.billingAddress.isAddressSameAsShipping
                && !this.billingAddress.isAddressSameAsShipping()
                    ? registry.get('dataScope = billingAddress.street.1')
                    : registry.get('dataScope = shippingAddress.street.1');
                if (street2Field) {
                    street2 = street2Field.value();
                }
            }
            const payload = {
                'id': this.getFieldValue('customerAddressId')
                    ? Number(this.getFieldValue('customerAddressId')) : null,
                'business_name': this.getFieldValue('company'),
                'country_code': countryId,
                'country': countryName,
                'city': this.getFieldValue('city'),
                'first_name': this.getFieldValue('firstname'),
                'last_name': this.getFieldValue('lastname'),
                'phone_number': this.getFieldValue('telephone'),
                'postal_code': this.getFieldValue('postcode'),
                'province': this.getFieldValue('region'),
                'province_code': this.getFieldValue('regionCode'),
                'address_line_1': street1,
                'address_line_2': street2,
            }
            try {
                this.validatePayload(payload);
            } catch (e) {
                return null;
            }
            return payload;
        },


        /**
         * Get address field value.
         *
         * @param field
         * @returns {*|string}
         */
        getFieldValue: function (field) {
            const useBilling = this.billingAddress
                && this.billingAddress.isAddressSameAsShipping
                && !this.billingAddress.isAddressSameAsShipping();

            return useBilling
                ? (this.billingAddress.hasOwnProperty(field) ? this.billingAddress[field] : '')
                : (this.shippingAddress.hasOwnProperty(field) ? this.shippingAddress[field] : '')
        },

        /**
         * Validate address payload.
         *
         * @param payload object
         * @return void
         * @throws Error
         * @private
         */
        validatePayload(payload) {
            let requiredFields = [
                'first_name',
                'last_name',
                'phone_number',
                'country',
                'address_line_1',
                'city',
            ];
            const country = this.countries.find(country => country.value === payload.country_code);
            if (country && country.is_region_required) {
                requiredFields.push('province');
                requiredFields.push('province_code');
            }
            if (country && country.is_zipcode_optional !== true) {
                requiredFields.push('postal_code');
            }
            _.each(requiredFields, function (field) {
                if (!payload[field]) {
                    throw new Error('Missing required field: ' + field);
                }
            })
        },
    }

    boldAddress.initialize();
    return boldAddress;
});
