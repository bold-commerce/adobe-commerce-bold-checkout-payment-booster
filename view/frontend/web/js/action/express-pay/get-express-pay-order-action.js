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
         * @param {String} gatewayId
         * @param {String} orderId
         * @returns {Deferred}
         */
        return function (gatewayId, orderId) {
            return platformClient.post(
                'rest/V1/express_pay/order/get',
                {
                    gatewayId: gatewayId,
                    orderId: orderId
                }
            );
        };
    }
);
