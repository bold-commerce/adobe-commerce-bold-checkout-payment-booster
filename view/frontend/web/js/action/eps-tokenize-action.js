define(
    [
        'Bold_CheckoutPaymentBooster/js/model/eps-client',
        'Magento_Checkout/js/model/quote'
    ],
    function (
        epsClient,
        quote
    ) {
        'use strict';

        /**
         * Tokenize EPS.
         */
        return async function (tokenId) {
            if (!window.checkoutConfig.bold.epsAuthToken || !window.checkoutConfig.bold.gatewayId) {
                return;
            }

            const path = '/{{configuration-group-label}}/tokenize'
            const body = {
                'version': 1,
                'auth_token': window.checkoutConfig.bold.epsAuthToken,
                'gateway_id': Number(window.checkoutConfig.bold.gatewayId),
                'tender_type': 'credit_card',
                'currency': quote.totals()['base_currency_code'],
                'payload_type': 'card_token',
                'payload': {
                    'card_token': tokenId,
                }
            };

            return await epsClient.post(
                path,
                body,
            );
        };
    });
