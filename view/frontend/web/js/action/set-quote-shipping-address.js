define(
    [
        'checkoutData',
        'Bold_CheckoutPaymentBooster/js/action/convert-fastlane-address',
        'Magento_Customer/js/model/address-list',
        'Magento_Checkout/js/action/select-shipping-address',
        'Bold_CheckoutPaymentBooster/js/action/hide-shipping-address-form'
    ], function (
        checkoutData,
        convertFastlaneAddressAction,
        addressList,
        selectShippingAddressAction,
        hideShippingAddressFormAction
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
            addressList([]);
            selectShippingAddressAction(shippingAddress);
            checkoutData.setSelectedShippingAddress(shippingAddress.getKey());
            hideShippingAddressFormAction();
        };
    });
