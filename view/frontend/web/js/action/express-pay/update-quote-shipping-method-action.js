define(
    [
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/shipping-service',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/save-shipping-information-action',
        'Magento_Checkout/js/action/select-shipping-method'
    ],
    function (
        quote,
        shippingService,
        saveShippingInformation,
        selectShippingMethodAction
    ) {
        'use strict';

        /**
         * Update quote shipping method and save shipping information.
         *
         * @param {Object} shippingMethod
         * @return void
         */
        return async function (shippingMethod = null) {
            let newMethod = null;
            if (shippingMethod !== null) {
                let availableMethods = shippingService.getShippingRates().filter((method) => {
                    let methodId = `${method.carrier_code}_${method.method_code}`;
                    methodId = methodId.replace(/\s/g, '');
                    return methodId === shippingMethod['id'] || methodId === shippingMethod['identifier'];
                });
                if (availableMethods.length > 0) {
                    newMethod = availableMethods[0];
                }
            } else {
                newMethod = shippingService.getShippingRates().first();
            }
            if (newMethod !== null) {
                selectShippingMethodAction(newMethod);
            }
            if (quote.guestEmail === null) {
                quote.guestEmail = 'test@test.com';
            }

            await saveShippingInformation();
        }
    }
);
