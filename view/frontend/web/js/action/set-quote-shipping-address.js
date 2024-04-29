define(
    [
        'checkoutData',
        'Bold_CheckoutPaymentBooster/js/action/convert-address',
        'Magento_Customer/js/model/address-list',
        'Magento_Checkout/js/action/select-shipping-address',
        'Bold_CheckoutPaymentBooster/js/action/hide-shipping-address-form'
    ], function (
        checkoutData,
        convertAddress,
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
            const shippingAddress = convertAddress(fastlaneAddress);
            addressList([shippingAddress]);
            selectShippingAddressAction(shippingAddress);
            checkoutData.setSelectedShippingAddress(shippingAddress.getKey());
            hideShippingAddressFormAction();
        };
    });
