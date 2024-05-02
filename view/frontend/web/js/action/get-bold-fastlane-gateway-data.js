define(
    [
        'Bold_CheckoutPaymentBooster/js/model/bold-frontend-client'
    ], function (
        boldFrontendClient
    ) {
        'use strict';

        return function () {
            /**
             * Retrieve Bold payments gateway data.
             *
             * @returns {Promise<unknown>}
             */
            return new Promise((resolve, reject) => {
                if (window.checkoutConfig.bold.gatewayData) {
                    resolve(window.checkoutConfig.bold.gatewayData);
                    return;
                }
                boldFrontendClient.get('paypal_fastlane/client_token').then((data) => {
                    window.checkoutConfig.bold.gatewayData = data;
                    resolve(data);
                }).catch(reject);
            });
        }
    });
