define([
    'Magento_Checkout/js/model/quote',
    'Bold_CheckoutPaymentBooster/js/model/fastlane',
    'Bold_CheckoutPaymentBooster/js/action/general/load-script-action',
    'Bold_CheckoutPaymentBooster/js/model/spi/callbacks/on-create-payment-order-callback',
    'Bold_CheckoutPaymentBooster/js/model/spi/callbacks/on-update-payment-order-callback',
    'Bold_CheckoutPaymentBooster/js/model/spi/callbacks/on-require-order-data-callback',
    'Bold_CheckoutPaymentBooster/js/model/spi/callbacks/on-approved-payment-order-callback',
    'Bold_CheckoutPaymentBooster/js/model/spi/callbacks/on-sca-payment-order-callback',
], function (
    quote,
    fastlane,
    loadScriptAction,
    onCreatePaymentOrderCallback,
    onUpdatePaymentOrderCallback,
    onRequireOrderDataCallback,
    onApprovedPaymentOrderCallback,
    onScaPaymentOrderCallback
) {
    'use strict';

    /**
     * Fastlane init model.
     *
     * @type {object}
     */
    return {
        /**
         * Load SPI SDK and initialize payments client instance.
         *
         * @returns {Promise<{}>}
         */
        getPaymentsClient: async function () {
            if (window.boldPaymentsInstance) {
                return window.boldPaymentsInstance;
            }
            if (window.createBoldPaymentsInstanceInProgress) {
                return new Promise((resolve) => {
                    const interval = setInterval(() => {
                        if (window.boldPaymentsInstance) {
                            clearInterval(interval);
                            resolve(window.boldPaymentsInstance);
                        }
                    }, 100);
                });
            }
            window.createBoldPaymentsInstanceInProgress = true;
            require.config({
                paths: {
                    bold_payments_sdk: window.checkoutConfig.bold.epsStaticUrl + '/js/payments_sdk',
                },
            });
            await new Promise((resolve, reject) => {
                require(['bold_payments_sdk'], resolve, reject);
            });
            const initialData = {
                'eps_url': window.checkoutConfig.bold.epsUrl,
                'eps_bucket_url': window.checkoutConfig.bold.epsStaticUrl,
                'group_label': window.checkoutConfig.bold.configurationGroupLabel,
                'trace_id': window.checkoutConfig.bold.publicOrderId,
                'payment_gateways': [
                    {
                        'gateway_id': Number(window.checkoutConfig.bold.gatewayId),
                        'auth_token': window.checkoutConfig.bold.epsAuthToken,
                        'currency': quote.totals()['base_currency_code'],
                    }
                ],
                'callbacks': {
                    'onCreatePaymentOrder': async (paymentType, paymentPayload) => {
                        return await onCreatePaymentOrderCallback(paymentType, paymentPayload);
                    },
                    'onUpdatePaymentOrder': async (paymentType, paymentPayload) => {
                        return await onUpdatePaymentOrderCallback(paymentType, paymentPayload);
                    },
                    'onApprovePaymentOrder': async (paymentType, paymentInformation, paymentPayload) => {
                        return await onApprovedPaymentOrderCallback(paymentType, paymentPayload);
                    },
                    'onScaPaymentOrder': async function (paymentType, paymentPayload) {
                        return await onScaPaymentOrderCallback(paymentType, paymentPayload);
                    },
                    'onRequireOrderData': async function (requirements) {
                        return onRequireOrderDataCallback(requirements);
                    },
                }
            };
            const paymentsInstance = new window.bold.Payments(initialData);
            window.boldFastlaneInstance = await fastlane.getFastlaneInstance(paymentsInstance);
            await paymentsInstance.initialize;
            if (paymentsInstance.paymentGateways[0]?.type === 'braintree') {
                await this._loadBraintreeScripts(paymentsInstance); //todo: remove as soon as payments.js is adapted to use requirejs
            }
            window.boldPaymentsInstance = paymentsInstance;
            window.createBoldPaymentsInstanceInProgress = false;
            return window.boldPaymentsInstance;
        },
        /**
         * Make sure Fastlane is initialized before payments instance is created.
         *
         * @return {Promise<*>}
         */
        getFastlaneInstance: async function () {
            if (window.boldFastlaneInstance) {
                return window.boldFastlaneInstance;
            }
            await this.getPaymentsClient();
            return window.boldFastlaneInstance;
        },
        /**
         * Load Braintree scripts via require js.
         *
         * @return {Promise<void>}
         */
        _loadBraintreeScripts: async function (paymentsInstance) {
            await loadScriptAction('bold_braintree_client', 'braintree.client');
            await loadScriptAction('bold_braintree_data_collector', 'braintree.dataCollector');
            const gatewayData = paymentsInstance.paymentGateways[0].credentials || null;
            if (!gatewayData) {
                return;
            }
            if (gatewayData.is_google_pay_enabled) {
                await loadScriptAction('bold_braintree_data_google_payment', 'braintree.googlePayment');
                await loadScriptAction('bold_google_pay');
            }
            if (gatewayData.is_apple_pay_enabled) {
                await loadScriptAction('bold_apple_pay', 'braintree.applePay');
            }
        },
    };
});
