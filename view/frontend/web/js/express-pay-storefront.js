define([
    'uiComponent',
    'ko',
    'Magento_Checkout/js/model/full-screen-loader',
    'Bold_CheckoutPaymentBooster/js/model/spi/callbacks/on-create-payment-order-callback',
    'Bold_CheckoutPaymentBooster/js/model/spi/callbacks/on-update-payment-order-callback',
    'Bold_CheckoutPaymentBooster/js/model/spi/callbacks/on-require-order-data-callback',
    'Bold_CheckoutPaymentBooster/js/model/spi/callbacks/on-approve-payment-order-callback',
    'Bold_CheckoutPaymentBooster/js/model/spi/callbacks/on-sca-payment-order-callback',
], function(
    Component,
    ko,
    fullScreenLoader,
    onCreatePaymentOrderCallback,
    onUpdatePaymentOrderCallback,
    onRequireOrderDataCallback,
    onApprovePaymentOrderCallback,
    onScaPaymentOrderCallback,
) {
    'use strict'

    return Component.extend({
        defaults: {
            config: ko.observable(null),
            element: ko.observable(null)
        },

        initialize: async function (config, element) {
            this._super();

            this.config(config);
            this.element(element);
            console.log({config}, {element});
            console.log(config.publicOrderId);

            if (this.config().isCartWalletPayEnabled) {
                await this._renderExpressPayments();
            }
        },

        _renderExpressPayments: async function () {
            const containerId = this.element().id;
            console.log('RENDER', containerId);

            let boldPaymentsInstance;

            try {
                boldPaymentsInstance = await this.getPaymentsClient();
            } catch (error) {
                console.error('Could not instantiate Bold Payments Client.', error);
            }

            console.log({boldPaymentsInstance});

            const allowedCountries = this._getAllowedCountryCodes();
            const walletOptions = {
                shopName: this.config().shopName ?? '',
                isPhoneRequired: this.config().isPhoneRequired ?? true,
                fastlane: this.config().fastlane,
                allowedCountryCodes: allowedCountries
            };
            boldPaymentsInstance.renderWalletPayments(containerId, walletOptions);
        },

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
            if (!require.defined('bold_payments_sdk')) {
                require.config({
                    paths: {
                        bold_payments_sdk: this.config().epsStaticUrl + '/js/payments_sdk',
                    },
                });
                await new Promise((resolve, reject) => {
                    require(['bold_payments_sdk'], resolve, reject);
                });
            }
            const initialData = {
                'eps_url': this.config().epsUrl,
                'eps_bucket_url': this.config().epsStaticUrl,
                'group_label': this.config().configurationGroupLabel,
                'trace_id': this.config().publicOrderId,
                'payment_gateways': [
                    {
                        'gateway_id': Number(this.config().gatewayId),
                        'auth_token': this.config().epsAuthToken,
                        'currency': this.config().baseCurrency,
                    }
                ],
                'callbacks': {
                    'onCreatePaymentOrder': async (paymentType, paymentPayload) => {
                        console.log('CALLBACK: onCreatePaymentOrder');
                        try {
                            return await onCreatePaymentOrderCallback(paymentType, paymentPayload);
                        } catch (e) {
                            console.error(e);
                            fullScreenLoader.stopLoader();
                            throw e;
                        }
                    },
                    'onUpdatePaymentOrder': async (paymentType, paymentPayload) => {
                        console.log('CALLBACK: onUpdatePaymentOrder', paymentType, paymentPayload);
                        try {
                            return await onUpdatePaymentOrderCallback(paymentType, paymentPayload);
                        } catch (e) {
                            console.error(e);
                            fullScreenLoader.stopLoader();
                            throw e;
                        }
                    },
                    'onApprovePaymentOrder': async (paymentType, paymentInformation, paymentPayload) => {
                        console.log('CALLBACK: onApprovePaymentOrder');
                        try {
                            return await onApprovePaymentOrderCallback(paymentType, paymentInformation, paymentPayload);
                        } catch (e) {
                            console.error(e);
                            fullScreenLoader.stopLoader();
                            throw e;
                        }
                    },
                    'onScaPaymentOrder': async function (paymentType, paymentPayload) {
                        console.log('CALLBACK: onScaPaymentOrder');
                        try {
                            return await onScaPaymentOrderCallback(paymentType, paymentPayload);
                        } catch (e) {
                            console.error(e);
                            fullScreenLoader.stopLoader();
                            throw e;
                        }
                    },
                    'onRequireOrderData': async function (requirements) {
                        console.log('CALLBACK: onRequireOrderData');
                        try {
                            return onRequireOrderDataCallback(requirements);
                        } catch (e) {
                            console.error(e);
                            fullScreenLoader.stopLoader();
                            throw e;
                        }
                    },
                    'onErrorPaymentOrder': function (errors) {
                        console.log('CALLBACK: onErrorPaymentOrder');
                        console.error('An unexpected PayPal error occurred', errors);
                        messageList.addErrorMessage({message: 'Warning: An unexpected error occurred. Please try again.'});
                    },
                }
            };

            console.log({initialData});
            const paymentsInstance = new window.bold.Payments(initialData);
            await paymentsInstance.initialize;
            if (paymentsInstance.paymentGateways[0]?.type === 'braintree') {
                await this._loadBraintreeScripts(paymentsInstance); //todo: remove as soon as payments.js is adapted to use requirejs
            }
            window.boldPaymentsInstance = paymentsInstance;
            window.createBoldPaymentsInstanceInProgress = false;
            return window.boldPaymentsInstance;
        },

        _getAllowedCountryCodes: function () {
            const countryCodes = [];
            _.each(this.config().countries, function (countryData) {
                countryCodes.push(countryData.value);
            });
            return countryCodes;
        },
    });
});
