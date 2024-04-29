define(
    [
        'Bold_CheckoutPaymentBooster/js/model/bold-frontend-client'
    ], function (
        boldFrontendClient
    ) {
        'use strict';

        return function () {
            /**
             * Retrieve Bold Fastlane gateway data.
             *
             * @returns {Promise<unknown>}
             */
            return new Promise((resolve, reject) => {
                boldFrontendClient.initialize(
                    window.checkoutConfig.bold_fastlane.jwtToken,
                    window.checkoutConfig.bold_fastlane.url
                );
                boldFrontendClient.get('paypal_fastlane/client_token').then((data) => {
                    resolve(data);
                }).catch(reject);
            });
        }
    });
