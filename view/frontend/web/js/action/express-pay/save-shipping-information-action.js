define(
    [
        'Magento_Checkout/js/model/quote',
        'mage/storage',
        'Magento_Checkout/js/model/resource-url-manager',
        'Magento_Checkout/js/model/shipping-save-processor/payload-extender',
        'Magento_Checkout/js/model/error-processor'
    ],
    function (
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
        return function (saveBillingAddress = false) {
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
    }
);
