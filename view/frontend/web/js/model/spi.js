define([
    'uiRegistry',
    'Bold_CheckoutPaymentBooster/js/action/convert-magento-address',
    'Bold_CheckoutPaymentBooster/js/action/create-wallet-pay-order-action',
    'Bold_CheckoutPaymentBooster/js/action/payment-sca-action',
    'Bold_CheckoutPaymentBooster/js/model/platform-client',
    'checkoutData',
    'Magento_Checkout/js/model/address-converter',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/resource-url-manager',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/model/shipping-save-processor/payload-extender',
    'Magento_Customer/js/customer-data',
    'mage/storage',
    'Bold_CheckoutPaymentBooster/js/model/fastlane',
    'Magento_Checkout/js/model/full-screen-loader',
], function (
    registry,
    convertMagentoAddress,
    createOrderAction,
    paymentScaAction,
    platformClient,
    checkoutData,
    addressConverter,
    errorProcessor,
    quote,
    resourceUrlManager,
    shippingService,
    payloadExtender,
    customerData,
    storage,
    fastlane,
    fullScreenLoader
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
                        try {
                            const walletPayResult = await createOrderAction(paymentPayload);

                            return { payment_data: { id: walletPayResult[0] }};
                        } catch (e) {
                            fullScreenLoader.stopLoader();
                            throw 'Unable to create order';
                        }
                    },
                    'onUpdatePaymentOrder': async (paymentType, paymentPayload) => {
                        const paymentData = paymentPayload['payment_data'];
                        this.updateAddress('shipping', paymentData['shipping_address']);
                        this.updateShippingMethod(paymentData['shipping_options']);

                        try {
                            await this.updateOrder(paymentData['order_id']);
                        } catch (e) {
                            fullScreenLoader.stopLoader();
                            throw new Error(`Update Payment Order Error ${e.message}`);
                        }
                    },
                    'onApprovePaymentOrder': async (paymentType, paymentInformation, paymentPayload) => {
                        const spi = registry.get('index = bold') ?? registry.get('index = bold-express-pay');

                        if (!spi) {
                            throw new Error('SPI component is not found');
                        }
                        spi.paymentId(paymentInformation.payment_id);
                        spi.paymentApprovalData(paymentPayload);
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
                        fullScreenLoader.stopLoader();
                        throw new Error('Unsupported payment type');
                    }.bind(this),
                    'onRequireOrderData' : async function (requirements) {
                        const payload = {};

                        for (const requirement of requirements) {
                            switch (requirement) {
                                case 'customer':
                                    let billingAddress = quote.billingAddress();
                                    const email = checkoutData.getValidatedEmailValue()
                                        ? checkoutData.getValidatedEmailValue()
                                        : window.checkoutConfig.customerData.email;

                                    payload[requirement] = {
                                        first_name: billingAddress.firstname,
                                        last_name: billingAddress.lastname,
                                        email_address: email,
                                    };
                                    break;
                                case 'items':
                                    payload[requirement] = quote.getItems().map(item => ({
                                        amount: parseInt(parseFloat(item.base_price) * 100),
                                        label: item.name
                                    }));
                                    break;
                                case 'billing_address':
                                    payload[requirement] = convertMagentoAddress(quote.billingAddress());
                                    break;
                                case 'shipping_address':
                                    payload[requirement] = convertMagentoAddress(quote.shippingAddress());
                                    break;
                                case 'shipping_options':
                                    payload[requirement] = shippingService.getShippingRates().map(option => ({
                                        label: `${option.carrier_title} - ${option.method_title}`,
                                        amount: option.amount,
                                        id: `${option.carrier_code}_${option.method_code}`
                                    }));
                                    break;
                                case 'totals':
                                    const totals = quote.getTotals();
                                    payload[requirement] = {
                                        order_total: totals()['grand_total'],
                                        order_balance: totals()['grand_total'],
                                        shipping_total: totals()['shipping_amount'],
                                        discounts_total: totals()['discount_amount'],
                                        taxes_total: totals()['tax'],
                                    };
                                    break;
                            }
                        }
                        return payload;
                    },
                }
            };

            const paymentInstance = new window.bold.Payments(initialData);
            this.fastlaneInstance = await fastlane.getFastlaneInstance(paymentInstance);
            this.paymentsInstance = paymentInstance;
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
         * @param {String} addressType
         * @param addressData
         */
        updateAddress: function(addressType, addressData) {
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
                address_type: addressType,
                firstname: addressData['first_name'] ?? null,
                lastname: addressData['last_name'] ?? null,
                street: [
                    addressData['address_line1'] ?? null,
                    addressData['address_line2'] ?? null,
                ],
                city: addressData['city'],
                region: {
                    region: regionName,
                    region_code: addressData['state'],
                    region_id: regionId
                },
                region_id: regionId,
                postcode: addressData['postal_code'],
                country_id: addressData['country_code'],
                email: addressData['email'] ?? null
            });

            if (addressType === 'shipping') {
                quote.shippingAddress(newAddress);
            } else {
                quote.billingAddress(newAddress);
            }
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

            if (quote.guestEmail === null) {
                quote.guestEmail = 'test@test.com';
            }

            this.saveShippingInformation();
        },

        /**
         * Update backend with new shipping information
         *
         * @returns {*}
         */
        saveShippingInformation: function (saveBillingAddress = false) {
            let payload;

            payload = {
                addressInformation: {
                    'shipping_address': quote.shippingAddress(),
                    'shipping_method_code': quote.shippingMethod()['method_code'],
                    'shipping_carrier_code': quote.shippingMethod()['carrier_code']
                }
            };

            if (saveBillingAddress) {
                payload.addressInformation.billing_address = quote.billingAddress();
            }

            payloadExtender(payload);

            return storage.post(
                resourceUrlManager.getUrlForSetShippingInformation(quote),
                JSON.stringify(payload)
            ).fail((response) => {
                errorProcessor.process(response);
            });
        }
    };
});
