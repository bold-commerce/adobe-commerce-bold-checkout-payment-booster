define([
    'underscore',
    'checkoutData',
    'Magento_Checkout/js/model/quote',
    'uiRegistry',
], function (
    _,
    checkoutData,
    quote,
    registry
) {
    'use strict';

    /**
     * Bold address model.
     */
    return {
        billingAddress: {},
        shippingAddress: {},
        /**
         * Get address api data.
         *
         * @return object
         */
        getAddress: function () {
            this.billingAddress = quote.billingAddress() || {};
            this.shippingAddress = quote.shippingAddress() || {};
            if (!this.billingAddress && !this.shippingAddress) {
                return null;
            }
            const postCode = registry.get('index = postcode')
            if (postCode && postCode.warn()) {
                return null;
            }
            const countryId = this.getFieldValue('countryId');
            const country = window.checkoutConfig.bold.countries.find(country => country.value === countryId);
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
                const street1Field = this.billingAddress.isAddressSameAsShipping
                && !this.billingAddress.isAddressSameAsShipping()
                    ? registry.get('dataScope = billingAddress.street.0')
                    : registry.get('dataScope = shippingAddress.street.0');
                if (street1Field) {
                    street1 = street1Field.value();
                }
            }
            if (!street2) {
                const street2Field = this.billingAddress.isAddressSameAsShipping
                && !this.billingAddress.isAddressSameAsShipping()
                    ? registry.get('dataScope = billingAddress.street.1')
                    : registry.get('dataScope = shippingAddress.street.1');
                if (street2Field) {
                    street2 = street2Field.value();
                }
            }
            const payload = {
                'email': customerData.email ?? checkoutData.getValidatedEmailValue(),
                'country_id': countryId,
                'company': this.getFieldValue('company'),
                'city': this.getFieldValue('city'),
                'firstname': this.getFieldValue('firstname'),
                'lastname': this.getFieldValue('lastname'),
                'telephone': this.getFieldValue('telephone'),
                'postcode': this.getFieldValue('postcode'),
                'region': this.getFieldValue('region'),
                'street': [street1, street2],
            }
            try {
                this.validatePayload(payload);
                return payload;
            } catch (e) {
                return null;
            }
        },

        /**
         * Get address field value with fallback.
         *
         * @param field
         * @returns {*|string}
         */
        getFieldValue: function (field) {
            let fieldValue = this.shippingAddress.hasOwnProperty(field) && !quote.isVirtual()
                ? this.shippingAddress[field]
                : null;
            if (fieldValue === null) {
                fieldValue = this.billingAddress.hasOwnProperty(field)
                    ? this.billingAddress[field]
                    : null;

            }
            return fieldValue;
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
                'firstname',
                'lastname',
                'telephone',
                'email',
                'country_id',
                'street',
                'city',
            ];
            const country = window.checkoutConfig.bold.countries.find(country => country.value === payload.country_id);
            if (country && country.is_region_required) {
                requiredFields.push('region');
            }
            if (country && country.is_zipcode_optional !== true) {
                requiredFields.push('postcode');
            }
            _.each(requiredFields, function (field) {
                if (!payload[field]) {
                    throw new Error('Missing required field: ' + field);
                }
            })
        },
    }
});
