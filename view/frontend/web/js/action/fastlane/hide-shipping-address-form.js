define(
    [
        'uiRegistry'
    ], function (
        registry
    ) {
        'use strict';

        /**
         * Hide new shipping address form action.
         */
        return function () {
            const newShippingAddress = registry.get('index = shippingAddress');
            const newShippingAddressFieldset = newShippingAddress.getChild('shipping-address-fieldset');
            newShippingAddress.isFormInline = false;
            if (!newShippingAddressFieldset) {
                console.error('Shipping address fieldset not found');
                return;
            }
            if (newShippingAddressFieldset.elems().length > 0) {
                window.checkoutConfig.bold.shippingAddressFields = newShippingAddressFieldset.elems();
            }
            newShippingAddressFieldset.elems([]);
        };
    });
