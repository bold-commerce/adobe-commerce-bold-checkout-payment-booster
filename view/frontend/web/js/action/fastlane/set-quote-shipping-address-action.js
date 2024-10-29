define(
    [
        'checkoutData',
        'Bold_CheckoutPaymentBooster/js/action/fastlane/convert-fastlane-address-action',
        'Magento_Checkout/js/action/select-shipping-address',
        'Magento_Checkout/js/action/select-billing-address',
        'Bold_CheckoutPaymentBooster/js/action/fastlane/hide-shipping-address-form-action',
        'Magento_Checkout/js/model/quote'
    ], function (
        checkoutData,
        convertFastlaneAddressAction,
        selectShippingAddressAction,
        selectBillingAddressAction,
        hideShippingAddressFormAction,
        quote
    ) {
        'use strict';

        /**
         * Set fastlane address as shipping address to quote action.
         *
         * @param {{}} fastlaneAddress
         * @return {void}
         */
        return function (fastlaneAddress) {
            const shippingAddress = convertFastlaneAddressAction(fastlaneAddress);
            selectShippingAddressAction(shippingAddress);
            selectBillingAddressAction(quote.shippingAddress());
            checkoutData.setSelectedShippingAddress(shippingAddress.getKey());
            hideShippingAddressFormAction();
        };
    });
