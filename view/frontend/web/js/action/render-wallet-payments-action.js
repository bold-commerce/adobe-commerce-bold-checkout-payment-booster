define([
        'Bold_CheckoutPaymentBooster/js/model/spi'
    ],
    function (
        spi
    ) {
        'use strict';

        /**
         * Render wallet payments only once on the page.
         *
         * @param {string} containerId
         * @returns {void}
         */
        return async function (containerId) {
            const boldPaymentsInstance = await spi.getPaymentsClient();
            const allowedCountries = window.checkoutConfig.bold?.countries ?? [];
            const walletOptions = {
                shopName: window.checkoutConfig.bold?.shopName ?? '',
                isPhoneRequired: window.checkoutConfig.bold?.isPhoneRequired ?? true,
                fastlane: window.checkoutConfig.bold?.fastlane,
                allowedCountryCodes: allowedCountries
            };
            if (typeof boldPaymentsInstance.walletPaymentsContainer !== 'undefined') {
                boldPaymentsInstance.walletPaymentsContainer.innerHTML = '';
            }
            await boldPaymentsInstance.renderWalletPayments(containerId, walletOptions);
        };
    });
