define(
    [
        'Magento_Checkout/js/model/new-customer-address',
        'Magento_Customer/js/customer-data',
        'Bold_CheckoutPaymentBooster/js/model/fastlane'
    ], function (
        NewCustomerAddressModel,
        customerData,
        fastlane
    ) {
        'use strict';
        /**
         * Convert fastlane address to Magento address considering fastlane address type.
         *
         * @param {Object} fastlaneAddress
         * @param {String} type
         * @return {{}}
         */
        return function (fastlaneAddress, type = null) {
            /**
             * Convert fastlane braintree address to Magento address.
             *
             * @param fastlaneAddress
             * @return {{firstname: *, city: *, prefix: *, vatId: *, suffix: *, countryId: *, isDefaultBilling: function(): *, regionCode: *|null, isEditable: function(): Boolean, street: *, customerId, company: *, fax: *, isDefaultShipping: function(): *, email: *, getKey: function(): String, extensionAttributes: *, postcode: *, saveInAddressBook: *, middlename: *, telephone: *, lastname: *, getCacheKey: function(): String, regionId, getType: function(): String, region: *|null, canUseForBilling: function(): Boolean, customAttributes: *}}
             * @private
             */
            const convertBraintreeAddress = (fastlaneAddress) => {
                const directoryData = customerData.get('directory-data');
                const regions = directoryData()[fastlaneAddress.countryCodeAlpha2].regions;
                let regionId = null;
                let regionName = null;
                if (regions !== undefined) {
                    Object.entries(regions).forEach(([key, value]) => {
                        if (value.code === fastlaneAddress.region) {
                            regionId = key;
                            regionName = value.name;
                        }
                    });
                }
                const convertedAddress = {
                    firstname: fastlaneAddress.firstName,
                    lastname: fastlaneAddress.lastName,
                    street: [fastlaneAddress.streetAddress, fastlaneAddress.extendedAddress],
                    city: fastlaneAddress.locality,
                    company: fastlaneAddress.company,
                    region: {
                        region: regionName,
                        region_code: fastlaneAddress.region || null,
                        region_id: regionId
                    },
                    region_id: regionId,
                    postcode: fastlaneAddress.postalCode,
                    country_id: fastlaneAddress.countryCodeAlpha2,
                    telephone: fastlaneAddress.phoneNumber,
                };
                return new NewCustomerAddressModel(convertedAddress);
            };

            /**
             * Convert fastlane PPCP address to Magento address.
             *
             * @param fastlaneAddress
             * @return {{firstname: *, city: *, prefix: *, vatId: *, suffix: *, countryId: *, isDefaultBilling: function(): *, regionCode: *|null, isEditable: function(): Boolean, street: *, customerId, company: *, fax: *, isDefaultShipping: function(): *, email: *, getKey: function(): String, extensionAttributes: *, postcode: *, saveInAddressBook: *, middlename: *, telephone: *, lastname: *, getCacheKey: function(): String, regionId, getType: function(): String, region: *|null, canUseForBilling: function(): Boolean, customAttributes: *}}
             * @private
             */
            const convertPPCPAddress = (fastlaneAddress) => {
                const directoryData = customerData.get('directory-data');
                const regions = directoryData()[fastlaneAddress.address.countryCode].regions;
                let regionId = null;
                let regionName = null;
                if (regions !== undefined) {
                    Object.entries(regions).forEach(([key, value]) => {
                        if (value.code === fastlaneAddress.address.adminArea1) {
                            regionId = key;
                            regionName = value.name;
                        }
                    });
                }
                const convertedAddress = {
                    firstname: fastlaneAddress.name.firstName,
                    lastname: fastlaneAddress.name.lastName,
                    street: [fastlaneAddress.address.addressLine1, fastlaneAddress.address.addressLine2],
                    city: fastlaneAddress.address.adminArea2,
                    company: fastlaneAddress.address.company,
                    region: {
                        region: regionName,
                        region_code: fastlaneAddress.address.adminArea1 || null,
                        region_id: regionId
                    },
                    region_id: regionId,
                    postcode: fastlaneAddress.address.postalCode,
                    country_id: fastlaneAddress.address.countryCode,
                    telephone: fastlaneAddress.phoneNumber.countryCode + fastlaneAddress.phoneNumber.nationalNumber,
                };
                return new NewCustomerAddressModel(convertedAddress);
            };

            let address;
            type = type || fastlane.getType();
            switch (type) {
                case 'braintree':
                    address = convertBraintreeAddress(fastlaneAddress);
                    break;
                case 'ppcp':
                    address = convertPPCPAddress(fastlaneAddress);
                    break;
            }
            address.getType = function () {
                return 'fastlane-shipping-address'
            };
            return address;
        };
    });
