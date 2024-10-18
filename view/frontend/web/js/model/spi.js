define([
    'uiRegistry',
    'Bold_CheckoutPaymentBooster/js/action/create-wallet-pay-order-action',
    'Bold_CheckoutPaymentBooster/js/action/payment-sca-action',
    'Magento_Checkout/js/model/quote',
    'Bold_CheckoutPaymentBooster/js/model/fastlane',
    'prototype'
], function (
    registry,
    createOrderAction,
    paymentScaAction,
    quote,
    fastlane
) {
    'use strict';

    /**
     * Fastlane init model.
     *
     * @type {object}
     */
    return {
        paymentsInstance: null,
        createPaymentsInstanceInProgress: false,

        /**
         * Load SPI SDK.
         *
         * @returns {Promise<void>}
         */
        getPaymentsClient: async function () {
            if (this.paymentsInstance) {
                return this.paymentsInstance;
            }
            if (this.createPaymentsInstanceInProgress) {
                return new Promise((resolve) => {
                    const interval = setInterval(() => {
                        if (this.paymentsInstance) {
                            clearInterval(interval);
                            resolve(this.paymentsInstance);
                        }
                    }, 100);
                });
            }
            this.createPaymentsInstanceInProgress = true;
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
                        if (paymentType !== 'ppcp') {
                            return;
                        }
                        const walletPayResult = await createOrderAction(paymentPayload);
                        if (walletPayResult.errors) {
                            return Promise.reject('An error occurred while processing your payment. Please try again.');
                        }
                        if (walletPayResult.data) {
                            return walletPayResult.data
                        } else {
                            throw 'Unable to create order';
                        }
                    },
                    'onUpdatePaymentOrder': async () => {
                        // Do nothing for now.
                    },
                    'onApprovePaymentOrder': async (paymentType, paymentPayload) => {
                        const spi = registry.get('index = bold');
                        if (!spi) {
                            throw new Error('SPI component is not found');
                        }
                        spi.paymentId(paymentPayload.payment_id);
                        spi.placeOrder({}, jQuery.Event());
                    },
                    'onScaPaymentOrder': async function (type, payload) {
                        if (type === 'ppcp') {
                            const scaResult = await paymentScaAction({
                                'gateway_type': 'ppcp',
                                'order_id': payload.order_id,
                                'public_order_id': window.checkoutConfig.bold.publicOrderId
                            });
                            return {card: scaResult};
                        }
                        throw new Error('Unsupported payment type');
                    }.bind(this)
                }
            };
            const paymentsInstance = new window.bold.Payments(initialData);
            this.fastlaneInstance = await fastlane.getFastlaneInstance(paymentsInstance);
            await paymentsInstance.initialize;
            this.paymentsInstance = paymentsInstance;
            this.createPaymentsInstanceInProgress = false;
            return this.paymentsInstance;
        },
        getFastlaneInstance: async function () {
            if (this.fastlaneInstance) {
                return this.fastlaneInstance;
            }
            await this.getPaymentsClient();
            return this.fastlaneInstance;
        },
    };
});
