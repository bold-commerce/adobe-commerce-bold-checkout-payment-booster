define(
    [
        'uiRegistry'
    ], function (
        registry
    ) {
        'use strict';

        /**
         * Show new shipping address form action.
         */
        return function () {
            const newShippingAddress = registry.get('index = shippingAddress');
            if (!newShippingAddress) {
                return;
            }
            const newShippingAddressFieldset = newShippingAddress.getChild('shipping-address-fieldset');
            newShippingAddress.isFormInline = true;
            if (!newShippingAddressFieldset) {
                console.error('Shipping address fieldset not found');
                return;
            }
            if (window.checkoutConfig.bold.shippingAddressFields) {
                newShippingAddressFieldset.elems(window.checkoutConfig.bold.shippingAddressFields);
            }
        };
    });
