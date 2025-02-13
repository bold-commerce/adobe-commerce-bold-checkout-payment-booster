define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'mage/storage',
        'Magento_Checkout/js/model/resource-url-manager',
        'Magento_Checkout/js/model/shipping-save-processor/payload-extender',
        'Magento_Checkout/js/model/error-processor'
    ],
    function (
        $,
        quote,
        storage,
        resourceUrlManager,
        payloadExtender,
        errorProcessor
    ) {
        'use strict';

        /**
         * Save shipping information.
         *
         * @param {Boolean} saveBillingAddress - Save billing address with shipping information.
         * @return {Deferred}
         */
        return async function (saveBillingAddress = false) {
            let promise = $.Deferred();

            let payload;
            payload = {
                addressInformation: {
                    'shipping_address': quote.shippingAddress(),
                    'shipping_method_code': quote.shippingMethod() ? quote.shippingMethod()['method_code'] : null,
                    'shipping_carrier_code': quote.shippingMethod() ? quote.shippingMethod()['carrier_code'] : null,
                }
            };
            if (saveBillingAddress) {
                payload.addressInformation.billing_address = quote.billingAddress();
            }
            payloadExtender(payload);
            storage.post(
                resourceUrlManager.getUrlForSetShippingInformation(quote),
                JSON.stringify(payload)
            ).done(
                function (response) {
                    quote.setTotals(response.totals);
                    return promise.resolve();
                }
            ).fail(
                function (response) {
                    errorProcessor.process(response);
                    return promise.reject();
                }
            );

            return promise;
        }
    }
);
