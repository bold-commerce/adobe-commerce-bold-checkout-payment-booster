define(
    [
        'jquery',
        'mage/url',
    ],
    function ($, urlBuilder) {
        'use strict';

        /**
         * @return {Promise<[{id: number, auth_token: string, currency: string}]>}
         */
        return async function () {
            const getPaymentGatewaysFormData = new FormData();

            let getPaymentGatewaysResponse;
            /** @var {[{id: number, auth_token: string, currency: string}]} getPaymentGatewaysResult */
            let getPaymentGatewaysResult;

            getPaymentGatewaysFormData.append('form_key', $.mage.cookies.get('form_key'));

            try {
                getPaymentGatewaysResponse = await fetch(
                    urlBuilder.build('bold_booster/digitalwallets_checkout/getPaymentGateways'),
                    {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: getPaymentGatewaysFormData,
                        credentials: 'same-origin'
                    }
                );
            } catch (error) {
                console.error('Could not fetch payment gateways. Error:', error);

                throw error;
            }

            getPaymentGatewaysResult = await getPaymentGatewaysResponse.json();

            if (!Array.isArray(getPaymentGatewaysResult)) {
                getPaymentGatewaysResult = [];
            }

            return getPaymentGatewaysResult;
        };
    }
);
