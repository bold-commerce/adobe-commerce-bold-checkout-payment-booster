define([
    'jquery',
    'Bold_CheckoutPaymentBooster/js/model/platform-client'
], function (
    $,
    platformClient
) {
    'use strict';

    /**
     * PayPal Express Pay init model.
     *
     * @type {object}
     */
    return {
        expressGatewayData: null,
        expressGatewayId: null,

        /**
         * Check if PPCP is configured.
         *
         * @return {Boolean}
         */
        isEnabled: function () {
            return Boolean(this.expressGatewayData && this.expressGatewayId);
        },

        /**
         * Load the PayPal sdk.
         *
         * @returns {Promise<void>}
         */
        loadPPCPSdk: async function() {
            await this.loadExpressGatewayData();

            if (!this.isEnabled()) {
                return;
            }

            const clientId = this.expressGatewayData.client_id;
            const testMode = this.expressGatewayData.is_test_mode;
            let parameters = '';

            if (testMode) {
                parameters = '&debug=true';
            }
            if (!require.defined('bold_paypal_sdk')){
                require.config({
                    paths: {
                        bold_paypal_sdk: 'https://www.paypal.com/sdk/js?client-id=' + clientId + '&components=buttons,fastlane&disable-funding=card&intent=authorize' + parameters,
                    },
                });
                await new Promise((resolve, reject) => {
                    require(['bold_paypal_sdk'], resolve, reject);
                });
            }
        },

        /**
         * Fetch and store Express Gateway Data
         *
         * @returns {Promise<void>}
         */
        loadExpressGatewayData: async function () {
            // Data already loaded
            if (this.isEnabled()) {
                return;
            }

            const storeUrl = new URL(window.checkoutConfig.bold.shopUrl);
            const clientDataUrl = window.checkoutConfig.bold.epsUrl + `/${storeUrl.hostname}/client_init`;
            const gatewayData = await $.ajax({
                url: clientDataUrl,
                type: 'GET',
            });
            this.expressGatewayId = Object.keys(gatewayData).find((gateway) => gatewayData[gateway].type === 'ppcp') ?? null;
            this.expressGatewayData = this.expressGatewayId ? gatewayData[this.expressGatewayId] : null;
        },

        /**
         * Create an express pay order
         *
         * @returns {Promise<*>}
         */
        createOrder: async function () {
            let url = 'rest/V1/express_pay/order/create';
            const gatewayId = this.expressGatewayId;

            if (!gatewayId) {
                return;
            }

            try {
                return await platformClient.post(
                    url,
                    {
                        quoteMaskId: window.checkoutConfig.quoteData.entity_id,
                        gatewayId: gatewayId,
                    }
                );
            } catch (e) {
                console.error(e);
            }
        }
    };
});
