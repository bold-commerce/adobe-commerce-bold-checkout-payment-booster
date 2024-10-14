define([
    'uiRegistry',
    'Bold_CheckoutPaymentBooster/js/action/create-wallet-pay-order-action',
    'Bold_CheckoutPaymentBooster/js/action/payment-sca-action',
    'Bold_CheckoutPaymentBooster/js/model/platform-client',
    'Magento_Checkout/js/model/address-converter',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/resource-url-manager',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/model/shipping-save-processor/payload-extender',
    'Magento_Customer/js/customer-data',
    'mage/storage',
    'prototype'
], function (
    registry,
    createOrderAction,
    paymentScaAction,
    platformClient,
    addressConverter,
    errorProcessor,
    quote,
    resourceUrlManager,
    shippingService,
    payloadExtender,
    customerData,
    storage
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
                        // console.log('onCreatePaymentOrder', {paymentType}, {paymentPayload});
                        if (paymentType !== 'ppcp') {
                            return;
                        }
                        try {
                            console.log('PAYMENT PAYLOAD', paymentPayload);
                            const walletPayResult = await createOrderAction(paymentPayload);

                            return { payment_data: { id: walletPayResult[0] }};
                        } catch (e) {
                            throw 'Unable to create order';
                        }
                    },
                    'onUpdatePaymentOrder': async (paymentType, paymentPayload) => {
                        console.log('onUpdatePaymentOrder', {paymentType}, {paymentPayload});
                        const paymentData = paymentPayload['payment_data'];
                        this.updateShippingAddress(paymentData['shipping_address']);
                        this.updateShippingMethod(paymentData['shipping_options']);

                        try {
                            await this.updateOrder(paymentData['order_id']);
                        } catch (e) {
                            throw new Error(`Update Payment Order Error ${e.message}`);
                        }
                    },
                    'onApprovePaymentOrder': async (paymentType, paymentPayload) => {
                        console.log('onApprovePaymentOrder', {paymentType}, {paymentPayload});
                        const spi = registry.get('index = bold') ?? registry.get('index = bold-express-pay');

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
            this.paymentsInstance = new window.bold.Payments(initialData);
            this.createPaymentsInstanceInProgress = false;
            return this.paymentsInstance;
        },

        // TODO: Abstract these functions into action components

        /**
         * Update express pay order
         *
         * @returns {Promise<*>}
         */
        updateOrder: async function (orderId) {
            let url = 'rest/V1/express_pay/order/update';

            return new Promise ((resolve, reject) => {
                platformClient.post(
                    url,
                    {
                        quoteMaskId: window.checkoutConfig.quoteData.entity_id,
                        gatewayId: window.checkoutConfig.bold.gatewayId,
                        paypalOrderId: orderId
                    }
                ).done(() => resolve()).fail(() => reject());
            });
        },

        /**
         * Update express pay order
         *
         * @param addressData
         */
        updateShippingAddress: function(addressData) {
            const directoryData = customerData.get('directory-data');
            let regions;

            try {
                regions = directoryData()[addressData['country_code']].regions;
            } catch (e) {
                regions = null;
            }

            let regionId = null;
            let regionName = null;
            if (regions !== null) {
                Object.entries(regions).forEach(([key, value]) => {
                    if (value.code === addressData['state']) {
                        regionId = key;
                        regionName = value.name;
                    }
                });
            }

            let newAddress = addressConverter.formAddressDataToQuoteAddress({
                address_type: 'shipping',
                city: addressData['city'],
                region: {
                    region: regionName,
                    region_code: addressData['state'],
                    region_id: regionId
                },
                region_id: regionId,
                postcode: addressData['postal_code'],
                country_id: addressData['country_code']
            });

            quote.shippingAddress(newAddress);
        },

        /**
         * Update the quote selected shipping method
         *
         * @param shippingMethod
         */
        updateShippingMethod: function (shippingMethod = null) {
            let newMethod = null;

            if (shippingMethod !== null) {
                let availableMethods = shippingService.getShippingRates().filter((method) => {
                    let methodId = `${method.carrier_code}_${method.method_code}`;
                    methodId = methodId.replace(/\s/g, '');

                    return methodId === shippingMethod['id'];
                });
                if (availableMethods.length > 0) {
                    newMethod = availableMethods[0];
                }
            } else {
                newMethod = shippingService.getShippingRates().first();
            }

            if (newMethod !== null) {
                quote.shippingMethod(newMethod);
            }

            quote.guestEmail = 'test@test.com';

            this.saveShippingInformation();
        },

        /**
         * Update backend with new shipping information
         *
         * @returns {*}
         */
        saveShippingInformation: function () {
            let payload;

            payload = {
                addressInformation: {
                    'shipping_address': quote.shippingAddress(),
                    'shipping_method_code': quote.shippingMethod()['method_code'],
                    'shipping_carrier_code': quote.shippingMethod()['carrier_code']
                }
            };

            payloadExtender(payload);

            storage.post(
                resourceUrlManager.getUrlForSetShippingInformation(quote),
                JSON.stringify(payload)
            ).fail((response) => {
                errorProcessor.process(response);
            });
        }
    };
});
