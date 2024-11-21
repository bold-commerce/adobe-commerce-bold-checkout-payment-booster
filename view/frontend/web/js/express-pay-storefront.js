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
            isVisible: ko.observable(false),
            config: ko.observable(null)
        },

        initialize: async function (config, element) {
            this._super();

            console.log({config}, {element});

            this.config(config);

            this._setVisibility();
        },

        /**
         * Set the visibility of the component.
         * @private
         */
        _setVisibility: function () {
            console.log('What is up express pay?');

            this.isVisible(!!this.config().isCartWalletPayEnabled);

            if (this.isVisible()) {
                this._renderExpressPayments();
            }
        },

        _renderExpressPayments: function () {

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
