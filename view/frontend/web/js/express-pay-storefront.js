define([
    'uiComponent',
    'underscore',
    'jquery'
], function (
    Component,
    _,
    $
) {
    'use strict'

    return Component.extend({
        defaults: {
            pageSource: '',
            containerId: '',
        },

        initialize: async function () {
            this._super();
            console.log('Bold Express Payments initialized for ' + this.pageSource);
            await this._getCheckoutConfig();
            require(['Bold_CheckoutPaymentBooster/js/model/spi'], (spi) => {
                this._renderExpressPayments(spi);
            });

        },

        _getCheckoutConfig: async function () {
            if (window.checkoutConfig) {
                return;
            }
            if (window.initExpressCheckoutInProcess) {
                new Promise(function (resolve) {
                    var interval = setInterval(function () {
                        if (!window.initExpressCheckoutInProcess) {
                            clearInterval(interval);
                            resolve();
                        }
                    }, 100);
                });
            }
            window.initExpressCheckoutInProcess = true;
            $.ajax({
                url: '/bold_booster/digitalwallets_checkout/getconfig',
                type: 'GET',
                dataType: 'json',
                async: false,
                success: function (checkoutConfig) {
                    window.checkoutConfig = checkoutConfig;
                    window.initExpressCheckoutInProcess = false;
                },
                fail: function () {
                    window.initExpressCheckoutInProcess = false;
                }
            });
        },

        _renderExpressPayments: async function (spi) {
            try {
                const boldPaymentsInstance = await spi.getPaymentsClient();
                const allowedCountries = this._getAllowedCountryCodes();
                const walletOptions = {
                    shopName: window.checkoutConfig.bold?.shopName ?? '',
                    isPhoneRequired: window.checkoutConfig.bold?.isPhoneRequired ?? true,
                    fastlane: window.checkoutConfig.bold?.fastlane,
                    allowedCountryCodes: allowedCountries,
                    pageSource: this.pageSource
                };

                boldPaymentsInstance.renderWalletPayments(this.containerId, walletOptions);
            } catch (error) {
                console.error('Could not instantiate Bold Payments Client.', error);
            }
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
