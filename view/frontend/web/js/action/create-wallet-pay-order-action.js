define([
    'Bold_CheckoutPaymentBooster/js/model/platform-client'
],
function (
    platformClient
) {
    'use strict';

    /**
     * Create Wallet Pay order.
     */
    return async function (paymentPayload) {
        return await platformClient.post(
            'rest/V1/express_pay/order/create',
            {
                quoteMaskId: window.checkoutConfig.quoteData.entity_id,
                paymentPayload,
            }
        );
    };
});
