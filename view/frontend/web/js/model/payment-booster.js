define([
    'Bold_CheckoutPaymentBooster/js/action/create-wallet-pay-order-action',
    'Bold_CheckoutPaymentBooster/js/model/address',
    'Bold_CheckoutPaymentBooster/js/model/platform-client',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/address-converter',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/model/shipping-save-processor/payload-extender',
    'Magento_Checkout/js/model/resource-url-manager',
    'Magento_Checkout/js/model/error-processor',
    'mage/storage',

], function(
    createOrderAction,
    addressModel,
    platformClient,
    customerData,
    addressConverter,
    fullscreenLoader,
    quote,
    shippingService,
    payloadExtender,
    resourceUrlManager,
    errorProcessor,
    storage
) {
    'use strict';

    /**
     *
     */
    return {
        initializeEps: async function () {
            if (!require.defined('bold_payments_sdk')) {
                require.config({
                    paths: {
                        bold_payments_sdk: window.checkoutConfig.bold.epsStaticUrl + '/js/payments_sdk',
                    },
                });
                await new Promise((resolve, reject) => {
                    require(['bold_payments_sdk'], resolve, reject);
                });
            }

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
                            throw 'Unable to create order';
                        }
                    },
                    'onUpdatePaymentOrder': async (paymentType, paymentPayload) => {
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
                        this.paymentId(paymentPayload.payment_id);
                        // this.placeOrder({}, jQuery.Event());
                    },
                }
            };
            window.bold.paymentsInstance = new window.bold.Payments(initialData);
        },

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
        },

        placeOrder: function (data, event) {
            fullscreenLoader.startLoader();
            const callback = this._super.bind(this);
            if (this.paymentId()) {
                callback(data, event);
                return;
            }
            this.tokenize()
            this.paymentId.subscribe((id) => {
                if (id != null) {
                    callback(data, event);
                }
            });
        },

        /**
         * Send tokenize action to SPI iframe.
         *
         * @return void
         */
        tokenize: function () {
            const iframeWindow = document.getElementById('spi_frame_SPI').contentWindow;
            const address = addressModel.getAddress();
            const payload = {
                billing_address: {
                    first_name: address.firstname,
                    last_name: address.lastname,
                    address_line_1: address.street[0],
                    address_line_2: address.street[1],
                    province_code: address.region,
                    city: address.city,
                    postal_code: address.postcode,
                    country_code: address.country_id,
                }
            };
            iframeWindow.postMessage({actionType: 'ACTION_SPI_TOKENIZE', payload: payload}, '*');
        },
    };
});
