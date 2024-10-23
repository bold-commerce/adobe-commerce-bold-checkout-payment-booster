define(
    [
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/shipping-service',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/save-shipping-information-action'
    ],
    function (
        quote,
        shippingService,
        saveShippingInformation
    ) {
        'use strict';

        /**
         * Update quote shipping method and save shipping information.
         *
         * @param {Object} shippingMethod
         * @return void
         */
        return function (shippingMethod = null) {
            let newMethod = null;
            if (shippingMethod !== null) {
                let availableMethods = shippingService.getShippingRates().filter((method) => {
                    let methodId = `${method.carrier_code}_${method.method_code}`;
                    methodId = methodId.replace(/\s/g, '');
                    return methodId === shippingMethod['id'];
                });
                if (availableMethods.length > 0) {
                    newMethod = availableMethods[0];
                }
            } else {
                newMethod = shippingService.getShippingRates().first();
            }
            if (newMethod !== null) {
                quote.shippingMethod(newMethod);
            }
            if (quote.guestEmail === null) {
                quote.guestEmail = 'test@test.com';
            }
            saveShippingInformation();
        }
    }
);
