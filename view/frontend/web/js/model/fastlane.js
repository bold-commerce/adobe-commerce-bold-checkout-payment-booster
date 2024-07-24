define([], function () {
    'use strict';

    /**
     * Fastlane init model.
     *
     * @type {object}
     */
    return {
        /**
         * Check if Fastlane flow is enabled and active.
         *
         * @return {Boolean}
         */
        isEnabled: function () {
            return window.checkoutConfig.bold
              && window.checkoutConfig.bold.fastlane
              && !window.isCustomerLoggedIn
        },
        /**
         * Retrieve Fastlane type (PPCP / Braintree).
         *
         * @return {string}
         */
        getType: function () {
            if (!window.checkoutConfig.bold.fastlane.gatewayData) {
                throw new Error('Fastlane instance is not initialized');
            }
            return window.checkoutConfig.bold.fastlane.gatewayData.type;
        },

        /**
         * Retrieve Gateway public ID.
         *
         * @returns {string}
         */
        getGatewayPublicId: function () {
            if (!window.checkoutConfig.bold.fastlane.gatewayData) {
                throw new Error('Fastlane instance is not initialized');
            }
            return window.checkoutConfig.bold.fastlane.gatewayData.gateway_public_id;
        },

        /**
         * Build Fastlane instance (PPCP / Braintree).
         *
         * @return {Promise<{profile: {showShippingAddressSelector: function}, identity: {lookupCustomerByEmail: function, triggerAuthenticationFlow: function}, FastlanePaymentComponent: function}>}
         */
        getFastlaneInstance: async function () {
            if (!this.isEnabled()) {
                return null;
            }
            if (window.boldFastlaneInstance) {
                return window.boldFastlaneInstance;
            }
            if (window.boldFastlaneInstanceCreateInProgress) {
                return new Promise((resolve) => {
                    const interval = setInterval(() => {
                        if (window.boldFastlaneInstance) {
                            clearInterval(interval);
                            resolve(window.boldFastlaneInstance);
                        }
                    }, 100);
                });
            }
            window.boldFastlaneInstanceCreateInProgress = true;
            try {
                const gatewayData = window.checkoutConfig.bold.fastlane.gatewayData;
                if (gatewayData.is_test_mode) {
                    window.localStorage.setItem('axoEnv', 'sandbox');
                    window.localStorage.setItem('fastlaneEnv', 'sandbox');
                }
                switch (gatewayData.type) {
                    case 'braintree':
                        await this.buildBraintreeFastlaneInstance(gatewayData);
                        break;
                    case 'ppcp':
                        await this.buildPPCPFastlaneInstance(gatewayData);
                        break;
                }
                this.setLocale();
                window.boldFastlaneInstanceCreateInProgress = false;
                return window.boldFastlaneInstance;
            } catch (e) {
                window.boldFastlaneInstanceCreateInProgress = false;
                console.error('Error creating Fastlane instance', e);
                return null;
            }
        },
        /**
         * Build Braintree Fastlane instance.
         *
         * @param {{client_token: string}} gatewayData
         * @return {Promise<void>}
         */
        buildBraintreeFastlaneInstance: async function (gatewayData) {
            await this.loadAxo();
            await this.loadScript('bold_braintree_fastlane_hosted_fields', 'hostedFields');
            const client = await this.loadScript('bold_braintree_fastlane_client');
            const dataCollector = await this.loadScript('bold_braintree_fastlane_data_collector');
            const fastlane = await this.loadScript('bold_braintree_fastlane');
            const clientInstance = await client.create(
              {
                  authorization: gatewayData.client_token,
              }
            );
            const dataCollectorInstance = await dataCollector.create(
              {
                  client: clientInstance,
              }
            );
            const styles = window.checkoutConfig.bold.fastlane.styles.length > 0
              ? window.checkoutConfig.bold.fastlane.styles.length
              : {};
            const {deviceData} = dataCollectorInstance;
            window.boldFastlaneInstance = await fastlane.create(
              {
                  authorization: gatewayData.client_token,
                  client: clientInstance,
                  deviceData: deviceData,
                  styles: styles
              }
            );
        },
        /**
         * Load Axo script with require js.
         *
         * @return {Promise<void>}
         */
        loadAxo: async function () {
            require.config({
                paths: {
                    bold_axo: 'https://www.paypalobjects.com/connect-boba/axo'
                },
                attributes: {
                    "bold_axo": {
                        'id': 'axo_id',
                        'async': null
                    }
                },
                onNodeCreated: function (node, config, name) {
                    if (config.attributes && config.attributes[name]) {
                        Object.keys(config.attributes[name]).forEach(attribute => {
                            node.setAttribute(attribute, config.attributes[name][attribute]);
                        });
                    }
                }
            });
            await new Promise((resolve, reject) => {
                require(['bold_axo'], resolve, reject);
            });
        },

        /**
         * Load given script with require js.
         *
         * @param type
         * @param variable
         * @return {Promise<unknown>}
         */
        loadScript: async function (type, variable = null) {
            return new Promise((resolve, reject) => {
                require([type], (src) => {
                    if (variable) {
                        window.braintree[variable] = src;
                    }
                    resolve(src);
                }, reject);
            });
        },

        /**
         * Build PPCP Fastlane instance.
         *
         * @param {{is_test_mode: boolean, client_id: string, client_token: string}} gatewayData
         * @return {Promise<void>}
         */
        buildPPCPFastlaneInstance: async function (gatewayData) {
            await this.loadAxo();
            await this.loadScript('bold_paypal_fastlane_hosted_fields', 'hostedFields');
            await this.loadScript('bold_paypal_fastlane_client', 'client');
            let debugMode = '';
            if (gatewayData.is_test_mode) {
                debugMode = '&debug=true';
            }
            require.config({
                paths: {
                    bold_paypal_fastlane: 'https://www.paypal.com/sdk/js?client-id=' + gatewayData.client_id + '&components=fastlane' + debugMode
                },
                shim: {
                    'bold_paypal_fastlane': {
                        exports: 'paypal.fastlane'
                    }
                },
                attributes: {
                    "bold_paypal_fastlane": {
                        'data-user-id-token': gatewayData.client_token,
                        'data-client-metadata-id': 'Magento2'
                    }
                },
                onNodeCreated: function (node, config, name) {
                    if (config.attributes && config.attributes[name]) {
                        Object.keys(config.attributes[name]).forEach(attribute => {
                            node.setAttribute(attribute, config.attributes[name][attribute]);
                        });
                    }
                }
            });
            await new Promise((resolve, reject) => {
                require(['bold_paypal_fastlane'], resolve, reject);
            });

            window.boldFastlaneInstance = await window.paypal.Fastlane();
        },

        /**
         * Set Fastlane locale.
         *
         * @returns {void}
         * @private
         */
        setLocale: function () {
            const availableLocales = [
                'en_us',
                'es_us',
                'fr_us',
                'zh_us',
            ];
            let locale = window.LOCALE
              ? window.LOCALE.toLowerCase().replace('-', '_')
              : 'en_us';
            if (!availableLocales.includes(locale)) {
                locale = 'en_us';
            }
            window.boldFastlaneInstance.setLocale(locale);
        }
    };
});
