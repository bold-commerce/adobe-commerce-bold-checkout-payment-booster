define(
    [
        'Bold_CheckoutPaymentBooster/js/model/eps-client',
    ],
    function (
        epsClient,
    ) {
        'use strict';

        /**
         * Initialize EPS client.
         */
        return async function () {
            if (!window.checkoutConfig.bold.gatewayId) {
                return;
            }
            const response = await epsClient.get('{{configuration-group-label}}/client_init?option=fastlane')
            return response[window.checkoutConfig.bold.gatewayId] || null;
        };
    });
