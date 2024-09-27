define([
    'jquery',
    'Bold_CheckoutPaymentBooster/js/model/platform-client',
    'Magento_Checkout/js/action/set-shipping-information',
    'Magento_Checkout/js/model/address-converter',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/action/select-shipping-method'
], function (
    $,
    platformClient,
    setShippingInformationAction,
    addressConverter,
    quote,
    shippingService,
    customerData,
    selectShippingMethodAction
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
        monitoringShippingRates: false,

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
            if (!require.defined('bold_paypal_sdk')){
                require.config({
                    paths: {
                        bold_paypal_sdk: 'https://www.paypal.com/sdk/js?client-id=' + clientId + '&components=buttons,fastlane&disable-funding=card&intent=authorize' + parameters,
                    },
                });
                await new Promise((resolve, reject) => {
                    require(['bold_paypal_sdk'], resolve, reject);
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

            // this.subscribeToShippingRates();
        },

        /**
         * Create an express pay order
         *
         * @returns {Promise<*>}
         */
        createOrder: async function () {
            let url = 'rest/V1/express_pay/order/create';
            const gatewayId = this.expressGatewayId;

            if (!gatewayId) {
                return;
            }

            // this.subscribeToShippingRates();

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
            console.log('UPDATE ORDER', quote.shippingMethod());
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
        updateQuoteShippingAddress: async function(addressData) {
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
            await setShippingInformationAction();
        },

        updateSelectedShippingMethod: async function (shippingMethod) {
            console.log('UPDATE SELECTED SHIPPING METHOD', shippingMethod);
            let availableMethods = null;

            if (shippingMethod !== null) {
                availableMethods = shippingService.getShippingRates().filter((method) => {
                    let methodId = `${method.carrier_code}_${method.method_code}`;
                    methodId = methodId.replace(/\s/g, '');

                    return methodId === shippingMethod['id'];
                });
            } else {
                availableMethods = shippingService.getShippingRates()[0];
            }

            console.log('AVAILABLE METHODS', availableMethods);

            if (availableMethods.length > 0) {
                quote.shippingMethod(availableMethods[0]);
                selectShippingMethodAction(availableMethods[0]);
                setShippingInformationAction();
                await this.updateOrder(this.expressOrderId);
                return new Promise.resolve();
                // selectShippingMethodAction(availableMethods[0]);
                // await setShippingInformationAction();
            } else {
                return new Promise.reject();
            }
        },

        // subscribeToShippingRates: function () {
        //     if (!this.monitoringShippingRates) {
        //         this.monitoringShippingRates = true;
        //         console.log('SETTING UP RATE MONITORING');
        //         shippingService.getShippingRates().subscribe(async (rates) => {
        //             console.log('RATES UPDATED', quote.shippingAddress(), rates);
        //             let shippingAddress = quote.shippingAddress();
        //             let hasRequiredAddressData = (
        //                 shippingAddress.city !== null
        //                 && shippingAddress.postcode !== null
        //                 && shippingAddress.country_id !== null);
        //             if (rates.length > 0 && hasRequiredAddressData) {
        //                 quote.shippingMethod(rates[0]);
        //                 await this.updateOrder(this.expressOrderId);
        //             }
        //         });
        //     }
        // }
    };
});
