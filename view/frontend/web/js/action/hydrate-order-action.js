define(
    [
        'underscore',
        'Bold_CheckoutPaymentBooster/js/model/platform-client',
        'Bold_CheckoutPaymentBooster/js/model/address'
    ], function (
        _,
        platformClient,
        addressModel,
    ) {
        'use strict';

        /**
         * Collect order data and send it to the backend.
         *
         * @return {void}
         */
        return async function (processExceptionCallback = console.error) {
            try {
                const urlTemplate = window.isCustomerLoggedIn
                    ? 'rest/V1/shops/{{shopId}}/cart/hydrate/:publicOrderId'
                    : 'rest/V1/shops/{{shopId}}/guest-cart/:cartId/hydrate/:publicOrderId';
                const url = urlTemplate.replace(':cartId', window.checkoutConfig.quoteData.entity_id)
                    .replace(':publicOrderId', window.checkoutConfig.bold.publicOrderId);
                const address = addressModel.getAddress();
                if (!address) {
                    console.log('Insufficient data to hydrate order');
                    return;
                }
                let payloadChanged = false;
                if (window.checkoutConfig.bold.hydratedOrderAddress) {
                    _.each(address, (value, key) => {
                        if (typeof value === 'object') {
                            _.each(value, (subValue, subKey) => {
                                if (window.checkoutConfig.bold.hydratedOrderAddress[key][subKey] !== subValue) {
                                    payloadChanged = true;
                                }
                            })
                            return;
                        }
                        if (window.checkoutConfig.bold.hydratedOrderAddress[key] !== value) {
                            payloadChanged = true;
                        }
                    });
                }
                if (window.checkoutConfig.bold.hydratedOrderAddress && !payloadChanged) {
                    console.log('Order address has not changed, skipping hydration');
                    return;
                }
                console.log('Hydrating order with address:', address);
                await platformClient.put(
                    url,
                    {
                        address: address,
                    }
                );
                window.checkoutConfig.bold.hydratedOrderAddress = Object.assign({}, address);
            } catch (e) {
                console.error('Error hydrating order', e);
                processExceptionCallback(e);
            }
        }
    });
