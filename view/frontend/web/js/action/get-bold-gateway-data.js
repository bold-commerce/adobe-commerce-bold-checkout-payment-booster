define(
    [
        'Bold_CheckoutPaymentBooster/js/model/bold-frontend-client'
    ], function (
        boldFrontendClient
    ) {
        'use strict';

        /**
         * Retrieve Bold payments gateway data.
         *
         * @returns {Promise<object>}
         */
        return function () {
            return new Promise((resolve, reject) => {
                if (window.checkoutConfig.bold.gatewayData) {
                    resolve(window.checkoutConfig.bold.gatewayData);
                    return;
                }
                boldFrontendClient.get('paypal_fastlane/client_token').then((response) => {
                    window.checkoutConfig.bold.gatewayData = response.data;
                    resolve(response.data);
                }).catch(reject);
            });
        }
    });
