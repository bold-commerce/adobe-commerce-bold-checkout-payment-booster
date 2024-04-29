define(
    [
        'Magento_Customer/js/model/address-list',
        'Bold_CheckoutPaymentBooster/js/action/show-shipping-address-form'
    ], function (
        addressList,
        showShippingAddressForm
    ) {
        'use strict';

        /**
         * Remove shipping address from quote and show new shipping address form action.
         */
        return function () {
            addressList([]);
            showShippingAddressForm();
        };
    });
