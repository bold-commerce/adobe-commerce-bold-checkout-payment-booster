define([
    'uiComponent',
    'underscore',
    'Bold_CheckoutPaymentBooster/js/model/spi',
    'Magento_Customer/js/customer-data'
], function(
    Component,
    _,
    spi,
    customerData
) {
    'use strict'

    return Component.extend({
        initialize: async function () {
            this._super();

            this._initConfig();
            this._setVisibility();
        },

        /**
         * Set the visibility of the component.
         * @private
         */
        _setVisibility: function () {
            const ppcpExpressContainer = document.getElementById('ppcp-express-payment');
            if (ppcpExpressContainer) {
                ppcpExpressContainer.remove();
            }

            this._renderExpressPayments();
        },

        _initConfig: async function () {
            if (!window?.checkoutConfig?.bold) {
                window.checkoutConfig.bold = customerData.get('bold-checkout-data')();
            }
        },

        _renderExpressPayments: async function () {
            const containerId = 'express-pay-buttons';

            let boldPaymentsInstance;

            try {
                boldPaymentsInstance = await spi.getPaymentsClient();
            } catch (error) {
                console.error('Could not instantiate Bold Payments Client.', error);

                return;
            }

            const allowedCountries = this._getAllowedCountryCodes();
            const walletOptions = {
                shopName: window.checkoutConfig.bold?.shopName ?? '',
                isPhoneRequired: window.checkoutConfig.bold?.isPhoneRequired ?? true,
                fastlane: window.checkoutConfig.bold?.fastlane,
                allowedCountryCodes: allowedCountries
            };

            boldPaymentsInstance.renderWalletPayments(containerId, walletOptions);
        },

        _getAllowedCountryCodes: function () {
            const countryCodes = [];
            _.each(window.checkoutConfig.bold?.countries, function (countryData) {
                countryCodes.push(countryData.value);
            });
            return countryCodes;
        },
    });
});
