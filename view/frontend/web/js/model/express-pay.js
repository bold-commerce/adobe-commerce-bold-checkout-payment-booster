define([
    'jquery',
    'Bold_CheckoutPaymentBooster/js/model/platform-client',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/address-converter',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/model/shipping-save-processor/payload-extender',
    'Magento_Checkout/js/model/resource-url-manager',
    'Magento_Checkout/js/model/error-processor',
    'mage/storage'
], function (
    $,
    platformClient,
    customerData,
    addressConverter,
    quote,
    shippingService,
    payloadExtender,
    resourceUrlManager,
    errorProcessor,
    storage
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
        expressOrderId: null,

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
            if (!require.defined('bold_paypal_fastlane')){
                require.config({
                    paths: {
                        bold_paypal_fastlane: 'https://www.paypal.com/sdk/js?client-id=' + clientId + '&components=buttons,fastlane&disable-funding=card&intent=authorize' + parameters,
                    },
                });
                await new Promise((resolve, reject) => {
                    require(['bold_paypal_fastlane'], resolve, reject);
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
        createExpressOrder: async function () {
            let url = 'rest/V1/express_pay/order/create';
            const gatewayId = this.expressGatewayId;

            if (!gatewayId) {
                return;
            }

            try {
                let createOrderResponse =  await platformClient.post(
                    url,
                    {
                        quoteMaskId: window.checkoutConfig.quoteData.entity_id,
                        gatewayId: gatewayId,
                    }
                );

                this.expressOrderId = createOrderResponse[0];
                return createOrderResponse;
            } catch (e) {
                console.error(e);
            }
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
                        gatewayId: this.expressGatewayId,
                        paypalOrderId: orderId
                    }
                ).done(() => resolve()).fail(() => reject());
            });
        },

        /**
         * Update quote shipping address
         *
         * @param addressData
         */
        updateQuoteShippingAddress: function(addressData) {
            const directoryData = customerData.get('directory-data');
            let regions;

            try {
                regions = directoryData()[addressData['countryCode']].regions;
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
                postcode: addressData['postalCode'],
                country_id: addressData['countryCode']
            });

            quote.shippingAddress(newAddress);
            this.updateSelectedShippingMethod();
        },

        /**
         * Update the quote selected shipping method
         *
         * @param shippingMethod
         */
        updateSelectedShippingMethod: function (shippingMethod = null) {
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
    };
});
