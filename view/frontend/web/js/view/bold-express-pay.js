define([
    'uiComponent',
    'ko',
    'jquery',
    'underscore',
    'Bold_CheckoutPaymentBooster/js/model/spi',
], function (
    Component,
    ko,
    $,
    _,
    spi,
) {
    'use strict';

    /**
     * PayPal Express pay button component.
     */
    return Component.extend({
        defaults: {
            template: 'Bold_CheckoutPaymentBooster/express-pay',
            paymentId: ko.observable(null),
            paymentApprovalData: ko.observable(null),
            isExpressPayLoading: ko.observable(false),
        },
        isVisible: ko.observable(false),
        /** @inheritdoc */

        initialize: async function () {
            this._super();

            this._setVisibility();
            window.addEventListener('hashchange', this._setVisibility.bind(this));
        },
        /**
         * Set the visibility of the component.
         * @private
         */
        _setVisibility: function () {
            const expressPayEnabled = window.checkoutConfig.bold?.isExpressPayEnabled;
            const onShippingStep = window.location.hash === '#shipping';
            this.isVisible(onShippingStep && expressPayEnabled);

            // On step change remove any other instance, can only have one on a page
            const ppcpExpressContainer = document.getElementById('ppcp-express-payment');
            if (ppcpExpressContainer) {
                ppcpExpressContainer.remove();
            }

            if (this.isVisible()) {
                this._renderExpressPayments();
            }
        },

        _renderExpressPayments: function () {
            const containerId = 'express-pay-buttons';
            const observer = new MutationObserver(async () => {
                let boldPaymentsInstance;

                if (document.getElementById(containerId)) {
                    this.isExpressPayLoading(true);
                    observer.disconnect();

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

                    await boldPaymentsInstance.renderWalletPayments(containerId, walletOptions);
                    this.isExpressPayLoading(false);
                }
            });
            observer.observe(document.documentElement, {
                childList: true,
                subtree: true
            });
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
