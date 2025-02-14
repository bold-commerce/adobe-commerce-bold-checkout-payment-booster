define(
    [
        'Bold_CheckoutPaymentBooster/js/model/platform-client'
    ],
    function (
        platformClient
    ) {
        'use strict';

        /**
         * Get Express Pay order
         *
         * @param {{}}
         * @returns {Promise}
         */
        return function (paymentApprovalData) {
            return platformClient.post('rest/V1/express_pay/callback/on_approve',
                {
                    quoteMaskId: window.checkoutConfig.quoteData.entity_id,
                    gatewayId: paymentApprovalData.gateway_id,
                    paypalOrderId: paymentApprovalData?.payment_data.order_id ?? paymentApprovalData.order_id
                }
            );
        };
    }
);
