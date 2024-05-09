define(
    [
        'Magento_Checkout/js/model/quote',
    ], function (
        quote
    ) {
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
             * @return {*|boolean}
             */
            isEnabled: function () {
                return window.checkoutConfig.bold
                    && window.checkoutConfig.bold.fastlane
                    && !window.isCustomerLoggedIn
                    && !quote.isVirtual()
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
                            if (!window.braintree) {
                                window.braintree = {};
                            }
                            await new Promise((resolve, reject) => {
                                require(['bold_braintree_fastlane_hosted_fields'], (hostedFields) => {
                                    window.braintree.hostedFields = hostedFields;
                                    resolve();
                                }, reject);
                            });
                            const clientInstance = await this.getBraintreeClientInstance(gatewayData.client_token);
                            const dataCollectorInstance = await this.getDataCollectorInstance(clientInstance);
                            const deviceData = dataCollectorInstance.deviceData;
                            const styles = {}
                            await new Promise((resolve, reject) => {
                                require(['bold_braintree_fastlane'], (bold_braintree_fastlane) => {
                                    window.braintree.fastlane = bold_braintree_fastlane;
                                    resolve();
                                }, reject);
                            });
                            window.boldFastlaneInstance = await window.braintree.fastlane.create({
                                authorization: gatewayData.client_token,
                                client: clientInstance,
                                deviceData: deviceData,
                                styles: styles
                            });
                            break;
                        case 'ppcp':
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
                            if (!window.braintree) {
                                window.braintree = {};
                            }
                            await new Promise((resolve, reject) => {
                                require(
                                    ['bold_paypal_fastlane_hosted_fields'],
                                    (hostedFields) => {
                                        window.braintree.hostedFields = hostedFields;
                                        resolve();
                                    }, reject);
                            });
                            await new Promise((resolve, reject) => {
                                require(
                                    ['bold_paypal_fastlane_client'],
                                    (client) => {
                                        window.braintree.client = client;
                                        resolve();
                                    },
                                    reject
                                );
                            });
                            await new Promise((resolve, reject) => {
                                require(['bold_paypal_fastlane'], resolve, reject);
                            });
                            window.boldFastlaneInstance = await window.paypal.Fastlane();
                            break;
                    }
                    this.setLocale();
                    window.boldFastlaneInstanceCreateInProgress = false;
                    return window.boldFastlaneInstance;
                } catch (e) {
                    window.boldFastlaneInstanceCreateInProgress = false;
                    throw new Error(
                        e.responseJSON && e.responseJSON.errors[0] ? e.responseJSON.errors[0].message : e.message
                    );
                }
            },

            /**
             * Retrieve Data Collector instance.
             *
             * @param {{}} client
             * @return {Promise<unknown>}
             * @private
             */
            getDataCollectorInstance: async function (client) {
                return new Promise((resolve, reject) => {
                    require(
                        ['bold_braintree_fastlane_data_collector'],
                        (bold_braintree_fastlane_data_collector) => {
                            window.braintree.dataCollector = bold_braintree_fastlane_data_collector;
                            window.braintree.dataCollector.create(
                                {
                                    client: client,
                                    riskCorrelationId: window.checkoutConfig.bold.publicOrderId
                                }
                            ).then((dataCollectorInstance) => {
                                resolve(dataCollectorInstance);
                            }).catch(reject);
                        },
                        reject
                    );
                });
            },

            /**
             * Retrieve Braintree Client instance.
             *
             * @param {string} token
             * @return {Promise}
             * @private
             */
            getBraintreeClientInstance: function (token) {
                return new Promise((resolve, reject) => {
                    require(
                        ['bold_braintree_fastlane_client'],
                        (bold_braintree_fastlane_client) => {
                            window.braintree.client = bold_braintree_fastlane_client;
                            window.braintree.client.create(
                                {
                                    authorization: token
                                }
                            ).then((client) => {
                                resolve(client);
                            }).catch(reject);
                        },
                        reject
                    );
                });
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
