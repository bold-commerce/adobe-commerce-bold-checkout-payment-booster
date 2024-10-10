define([
    'uiComponent',
    'ko',
    'Bold_CheckoutPaymentBooster/js/model/payment-booster'
], function (
    Component,
    ko,
    paymentBooster
) {
    'use strict';

    /**
     * PayPal Express pay button component.
     */
    return Component.extend({
        defaults: {
            template: 'Bold_CheckoutPaymentBooster/express-pay'
        },
        isVisible: ko.observable(false),
        initialize: async function () {
            this._super();

            this._setVisibility();
            window.addEventListener('hashchange', this._setVisibility.bind(this));

            const containerId = 'express-pay-buttons';
            const observer = new MutationObserver(async () => {
                if (document.getElementById(containerId)) {
                    observer.disconnect();

                    if (!window.bold?.paymentsInstance) {
                        await paymentBooster.initializeEps();
                    }

                    const allowedCountries = window.checkoutConfig.bold.countries;
                    const walletOptions = {
                        shopName: window.checkoutConfig.bold.shopName,
                        isPhoneRequired: window.checkoutConfig.bold.isPhoneRequired,
                        fastlane: window.checkoutConfig.bold.fastlane,
                        allowedCountryCodes: allowedCountries
                    };
                    window.bold.paymentsInstance.renderWalletPayments(containerId, walletOptions);
                }
            });
            observer.observe(document.documentElement, {
                childList: true,
                subtree: true
            });
        },
        /**
         * Set the visibility of the component.
         * @private
         */
        _setVisibility: function () {
            // this.isVisible(window.location.hash === '#shipping' && expressPay.isEnabled());
            this.isVisible(window.location.hash === '#shipping');
        }
    });
});
