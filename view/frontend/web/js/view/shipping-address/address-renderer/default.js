define([
    'Magento_Checkout/js/view/shipping-address/address-renderer/default',
    'Bold_CheckoutPaymentBooster/js/action/fastlane/show-fastlane-shipping-address-form'
], function (
    MagentoAddressRenderer,
    showFastlaneAddressFormAction
) {
    'use strict';

    /**
     * Fastlane address renderer.
     */
    return MagentoAddressRenderer.extend({
        /**
         * Show fastlane address modal form instead of Magento modal form and set selected address to quote.
         *
         * {@inheritdoc}
         */
        editAddress: function () {
            showFastlaneAddressFormAction();
        }
    });
});
