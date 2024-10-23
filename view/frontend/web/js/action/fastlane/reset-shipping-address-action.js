define(
    [
        'Magento_Checkout/js/model/quote',
        'Bold_CheckoutPaymentBooster/js/action/fastlane/show-shipping-address-form-action'
    ], function (
        quote,
        showShippingAddressForm,
    ) {
        'use strict';

        /**
         * Remove shipping address from quote and show new shipping address form action.
         */
        return function () {
            const quoteShippingAddress = quote.shippingAddress();
            if (quoteShippingAddress.getType() !== 'fastlane-shipping-address') {
                return;
            }
            quoteShippingAddress.getType = function () {
                return 'new-customer-address';
            };
            quote.shippingAddress(quoteShippingAddress);
            showShippingAddressForm();
        };
    });
