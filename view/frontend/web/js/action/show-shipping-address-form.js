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
            const newShippingAddressFieldset = newShippingAddress.getChild('shipping-address-fieldset');
            newShippingAddress.isFormInline = true;
            if (!newShippingAddressFieldset) {
                console.error('Shipping address fieldset not found');
                return;
            }
            if (window.checkoutConfig.shippingAddressFields) {
                newShippingAddressFieldset.elems(window.checkoutConfig.shippingAddressFields);
            }
        };
    });
