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
            let newMethod;
            let timeoutMS = 100;
            let carry = 100;
            while (shippingService.isLoading() && timeoutMS < 5000) {
                // max total timeout is 8000ms
                await new Promise(resolve => setTimeout(resolve, timeoutMS));
                carry = timeoutMS + carry;
                timeoutMS = carry - timeoutMS;
            }
            shippingMethod = shippingMethod ?? quote.shippingMethod();
            if (shippingMethod !== null) {
                newMethod = shippingService.getShippingRates().filter((method) => {
                    let methodId = `${method.carrier_code}_${method.method_code}`;
                    methodId = methodId.replace(/\s/g, '');
                    return methodId === shippingMethod['id']
                        || methodId === shippingMethod['identifier']
                        || methodId === `${shippingMethod.carrier_code}_${shippingMethod.method_code}`;
                })[0];
            }
            selectShippingMethodAction(newMethod ?? shippingService.getShippingRates().first());
            if (quote.guestEmail === null) {
                quote.guestEmail = 'test@test.com';
            }

            await saveShippingInformation();
        }
    }
);
