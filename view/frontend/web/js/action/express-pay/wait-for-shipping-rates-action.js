define(
    [
        'Magento_Checkout/js/model/shipping-service'
    ],
    function (shippingService) {
        'use strict';

        /**
         * Wait for Magento shipping rate collection to finish after an address change.
         *
         * @param {{maxWaitMs?: number, requireRates?: boolean}} options
         * @returns {Promise<Array>}
         */
        return async function (options = {}) {
            const maxWaitMs = options.maxWaitMs ?? 8000;
            const requireRates = options.requireRates ?? false;
            let timeoutMS = 100;
            let carry = 100;
            const start = Date.now();

            while (shippingService.isLoading() && Date.now() - start < maxWaitMs) {
                await new Promise(function (resolve) {
                    setTimeout(resolve, timeoutMS);
                });
                carry = timeoutMS + carry;
                timeoutMS = carry - timeoutMS;
            }

            if (requireRates) {
                const deadline = start + maxWaitMs;

                while (Date.now() < deadline) {
                    const rates = shippingService.getShippingRates()();

                    if (rates.length > 0) {
                        return rates;
                    }

                    await new Promise(function (resolve) {
                        setTimeout(resolve, 200);
                    });
                }
            }

            return shippingService.getShippingRates()();
        };
    }
);
