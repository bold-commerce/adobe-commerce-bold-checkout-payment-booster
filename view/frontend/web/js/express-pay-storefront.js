define([
    'uiComponent',
    'ko'
    // 'Bold_CheckoutPaymentBooster/Model/CheckoutData'
], function(
    Component,
    ko
    // checkoutData
) {
    'use strict'

    return Component.extend({
        defaults: {
            config: ko.observable(null)
        },

        initialize: async function (config, element) {
            this._super();

            this.config(config);
            console.log({config}, {element});

            if (this.config().isCartWalletPayEnabled) {
                this._renderExpressPayments();
            }
        },

        _renderExpressPayments: function () {
            console.log('RENDER');
            // const containerId = 'express-pay-buttons';
            //
            // let boldPaymentsInstance;
            //
            // try {
            //     boldPaymentsInstance = await spi.getPaymentsClient();
            // } catch (error) {
            //     console.error('Could not instantiate Bold Payments Client.', error);
            //
            //     return;
            // }

            // const allowedCountries = this._getAllowedCountryCodes();
            // const walletOptions = {
            //     shopName: window.checkoutConfig.bold?.shopName ?? '',
            //     isPhoneRequired: window.checkoutConfig.bold?.isPhoneRequired ?? true,
            //     fastlane: window.checkoutConfig.bold?.fastlane,
            //     allowedCountryCodes: allowedCountries
            // };
            //
            // boldPaymentsInstance.renderWalletPayments(containerId, walletOptions);
        }
    });
});
