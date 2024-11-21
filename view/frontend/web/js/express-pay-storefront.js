define([
    'uiComponent',
    // 'Bold_CheckoutPaymentBooster/Model/CheckoutData'
], function(
    Component,
    // checkoutData
) {
    'use strict'

    return Component.extend({
        initialize: async function (config, element) {
            this._super();

            console.debug({config}, {element});

            this._setVisibility();
        },

        /**
         * Set the visibility of the component.
         * @private
         */
        _setVisibility: function () {
            console.log('What is up express pay?');
            // const expressPayEnabled = window.checkoutConfig.bold?.isExpressPayEnabled;
            // const onShippingStep = window.location.hash === '#shipping';
            // this.isVisible(onShippingStep && expressPayEnabled);

            // On step change remove any other instance, can only have one on a page
            // const ppcpExpressContainer = document.getElementById('ppcp-express-payment');
            // if (ppcpExpressContainer) {
            //     ppcpExpressContainer.remove();
            // }

            // if (this.isVisible()) {
                this._renderExpressPayments();
            // }
        },

        _renderExpressPayments: async function () {

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
