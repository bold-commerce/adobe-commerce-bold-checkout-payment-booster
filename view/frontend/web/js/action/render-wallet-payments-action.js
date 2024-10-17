define([
        'Bold_CheckoutPaymentBooster/js/model/spi',
        'Bold_CheckoutPaymentBooster/js/model/fastlane'
    ],
    function (
        spi,
        fastlane
    ) {
        'use strict';

        /**
         * Render wallet payments only once on the page.
         *
         * @param {string} containerId
         * @returns {void}
         */
        return async function (containerId) {
            await new Promise((resolve) => {
                const interval = setInterval(() => {
                    if (!window.boldWalletPayRenderInProgress) {
                        clearInterval(interval);
                        resolve();
                    }
                }, 100);
            });
            window.boldWalletPayRenderInProgress = true;
            await fastlane.getFastlaneInstance(); // todo: investigate why express pay is getting destroyed in case fastlane is initialized after render.
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
            window.boldWalletPayRenderInProgress = false;
        };
    });
