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
        /**
         * Check if PPCP is configured.
         *
         * @return {Boolean}
         */
        isEnabled: function () {
            // TODO Update this check so we can delete this file
            // return Boolean(this.expressGatewayData && this.expressGatewayId);
            return true;
        },
    };
});
