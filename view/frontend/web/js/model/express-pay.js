define([], function () {
    'use strict';

    /**
     * PayPal Express Pay init model.
     *
     * @type {object}
     */
    return {
        /**
         * Check if PPCP is configured.
         *
         * @return {Boolean}
         */
        isEnabled: function () {
            // partner_id only exists when PPCP is configured
            return Boolean(this._getPpcpPaymentMethod()?.partner_id);
        },

        /**
         * Retrieve button styles.
         *
         * @returns {string}
         */
        getStyles: function () {
            const style = this._getPpcpPaymentMethod()?.style;

            if (!style) {
                throw new Error('PayPal Express Pay is not configured');
            }
            return style;
        },

        /**
         * Load the PayPal sdk.
         *
         * @returns {Promise<void>}
         */
        loadPPCPSdk: async function() {
            const ppcpPaymentMethod = this._getPpcpPaymentMethod();
            const partnerId = ppcpPaymentMethod?.partner_id;
            const testMode = ppcpPaymentMethod?.is_dev;
            let parameters = '';
            if (testMode) {
                parameters = '&debug=true';
            }
            if (!require.defined('bold_paypal_sdk')){
                require.config({
                    paths: {
                        bold_paypal_sdk: 'https://www.paypal.com/sdk/js?client-id=' + partnerId + '&components=buttons,fastlane&disable-funding=card&intent=authorize' + parameters,
                    },
                });
                await new Promise((resolve, reject) => {
                    require(['bold_paypal_sdk'], resolve, reject);
                });
            }
        },

        /**
         * Retrieve data for PPCP payment method
         *
         * @returns {Object|null}
         * @private
         */
        _getPpcpPaymentMethod: function () {
            return window.checkoutConfig?.bold?.alternativePaymentMethods?.find(
                (paymentMethod) => paymentMethod.type === 'paypal_commerce_platform'
            ) ?? null;
        },
    };
});
