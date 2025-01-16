define([
    'uiComponent',
    'underscore',
    'Bold_CheckoutPaymentBooster/js/model/spi',
    'Magento_Customer/js/customer-data'
], function (
    Component,
    _,
    spi,
    customerData
) {
    'use strict'

    return Component.extend({
        initialize: async function () {
            this._super();

            await this._initConfig();
            await this._renderExpressPayments();
        },

        _initConfig: async function () {
            if (!window?.checkoutConfig?.bold?.epsStaticUrl) {
                await customerData.reload(['bold-checkout-data']);
            }

            if (!window?.checkoutConfig) {
                window.checkoutConfig = await customerData.get('bold-checkout-data')()[checkoutConfig];
            }

            if (!window?.checkoutConfig?.bold || !window?.checkoutConfig?.bold?.epsStaticUrl) {
                window.checkoutConfig.bold = await customerData.get('bold-checkout-data')()['checkoutConfig']['bold'];
            }
        },

        _renderExpressPayments: async function () {
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
                allowedCountryCodes: allowedCountries,
                pageSource: this.pageSource
            };

            boldPaymentsInstance.renderWalletPayments(this.containerId, walletOptions);
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
