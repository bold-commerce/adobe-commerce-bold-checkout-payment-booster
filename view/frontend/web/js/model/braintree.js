define([
    'Bold_CheckoutPaymentBooster/js/model/spi',
    'Magento_Checkout/js/model/quote',
    'underscore',
    'prototype'
], function (
    spi,
    quote,
    _,
) {
    'use strict';

    /**
     * Fastlane init model.
     *
     * @type {object}
     */
    return {
        gatewayData: null,

        /**
         * Check if Braintree PayPal flow is enabled and active.
         *
         * @return {Boolean}
         */
        isAvailable: function () {
            return true; // todo: add logic to check if Braintree PayPal buttons are available.
        },

        /**
         * Render Braintree PayPal buttons in given container.
         *
         * @param containerId
         * @return {Promise<void>}
         */
        renderButtons: async function (containerId) {
            await this._initBraintreeButtons();
            if (!window.bold.braintreeButtons) {
                return;
            }
            window.bold.braintreeButtons.render('#' + containerId);
        },
        /**
         * Build Braintree PayPal buttons instance.
         *
         * @return {Promise<void>}
         * @private
         */
        _initBraintreeButtons: async function () {
            if (!this.isAvailable()) {
                return;
            }
            if (window.bold.braintreeButtons) {
                return window.bold.braintreeButtons;
            }
            window.bold.braintreeButtons = null;
            if (window.bold.braintreeButtonsCreateInProgress) {
                return new Promise((resolve) => {
                    const interval = setInterval(() => {
                        if (window.window.bold.braintreeButtons) {
                            clearInterval(interval);
                            resolve();
                        }
                    }, 100);
                });
            }
            window.bold.braintreeButtonsCreateInProgress = true;
            try {
                const boldPaymentsInstance = await spi.getPaymentsClient();
                _.each(boldPaymentsInstance.paymentGateways, function (gateway) {
                    if (gateway.gateway_id === window.checkoutConfig.bold.gatewayId) {
                        this.gatewayData = gateway;
                    }
                }.bind(this));
                if (!this.gatewayData) {
                    window.bold.braintreeButtonsCreateInProgress = false;
                    return null;
                }
                if (this.gatewayData.type !== 'braintree') {
                    window.bold.braintreeButtonsCreateInProgress = false;
                    return null;
                }
                if (!window.braintree) {
                    window.braintree = {};
                }
                await this._initScripts();
                await this._initializeButtons();
                window.bold.braintreeButtonsCreateInProgress = false;
            } catch (e) {
                window.bold.braintreeButtonsCreateInProgress = false;
                console.error('Error creating Braintree PayPal client instance', e);
            }
        },
        _initScripts: async function () {
            this._addVersionToBraintreeCheckout();
            await this._loadScript('bold_braintree_client', 'braintree.client');
            await this._loadScript('bold_braintree_paypal_checkout', 'braintree.paypalCheckout');
        },
        _initializeButtons: async function () {
            const clientInstance = await braintree.client.create({
                authorization: this.gatewayData.credentials.tokenization_key,
            });
            const paypalCheckoutInstance = await braintree.paypalCheckout.create({client: clientInstance});
            const paypalSDK = await paypalCheckoutInstance.loadPayPalSDK({
                currency: quote.totals()['base_currency_code'],
                intent: 'authorize'
            });
            window.bold.braintreeButtons = await paypal.Buttons({
                fundingSource: paypal.FUNDING.PAYPAL,
                createOrder: function () {
                    // todo: add logic to create order
                },
                onShippingChange: function (data, actions) {
                    // todo: add logic to update shipping address
                },
                onApprove: function (data, actions) {
                    //todo: add logic to approve payment
                },
                onCancel: function (data) {
                    // todo: add logic to cancel payment
                },
                onError: function (err) {
                    // todo: add logic to handle error
                }
            });
        },
        /**
         * Load given script with require js.
         *
         * @param {string} type
         * @param {string} variable
         * @return {Promise<unknown>}
         * @private
         */
        _loadScript: async function (type, variable = null) {
            return new Promise((resolve, reject) => {
                require([type], (src) => {
                    if (!variable) {
                        resolve(src);
                        return;
                    }
                    const variableParts = variable.split('.');
                    let current = window;
                    for (let i = 0; i < variableParts.length; i++) {
                        if (!current[variableParts[i]]) {
                            current[variableParts[i]] = {};
                        }
                        if (i === variableParts.length - 1) {
                            current[variableParts[i]] = src;
                        }
                        current = current[variableParts[i]];
                    }
                    resolve(src);
                }, reject);
            });
        }
        ,

        /**
         * Version script attribute for Braintree Checkout script.
         *
         * @return {void}
         * @private
         */
        _addVersionToBraintreeCheckout: function () {
            Element.prototype.appendChild = Element.prototype.appendChild.wrap(
                function (appendChild, element) {
                    if (element.attributes && element.attributes['data-requiremodule']?.value === 'bold_braintree_checkout') {
                        // Require.js < 2.1.19 is not calling onNodeCreated config callback, so we need to set the client token manually.
                        element.setAttribute('data-version-4', null);
                    }
                    return appendChild(element);
                });
        }
        ,
    };
})
;
