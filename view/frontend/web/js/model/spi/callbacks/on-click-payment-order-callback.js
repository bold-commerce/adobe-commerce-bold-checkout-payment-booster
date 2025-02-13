define(
    [
        'Bold_CheckoutPaymentBooster/js/action/digital-wallets/create-quote',
    ],
    function (createQuote) {
        'use strict';

        /**
         * @param {Object} paymentType
         * @param {Object} paymentPayload
         * @returns {Promise<void>}
         * @throws Error
         */
        return async function (paymentType, paymentPayload) {
            if (!paymentPayload.containerId.includes('product-detail')) {
                return;
            }

            await createQuote();
        };
    }
);
