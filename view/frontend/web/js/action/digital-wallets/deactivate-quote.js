define(
    [
        'jquery',
        'mage/url'
    ],
    function ($, urlBuilder) {
        'use strict';

        return function () {
            const deactivateQuoteFormData = new FormData();

            if (
                !window.checkoutConfig.quoteData?.hasOwnProperty('entity_id')
                || window.checkoutConfig.quoteData.entity_id.length === 0
            ) {
                return;
            }

            deactivateQuoteFormData.append('form_key', $.mage.cookies.get('form_key'));
            deactivateQuoteFormData.append('quote_id', window.checkoutConfig.quoteData.entity_id);

            fetch(
                urlBuilder.build('bold_booster/digitalwallets_quote/deactivate'),
                {
                    method: 'POST',
                    headers: {
                        'X_REQUESTED_WITH': 'XMLHttpRequest'
                    },
                    body: deactivateQuoteFormData,
                    credentials: 'same-origin'
                }
            ).then(
                async response => {
                    const result = await response.json();

                    if (result.success) {
                        return;
                    }

                    console.error(result.error);
                }
            ).then(
                () => {
                    Object.keys(window.checkoutConfig.quoteData).forEach(
                        key => {
                            delete window.checkoutConfig.quoteData[key];
                        }
                    );

                    window.checkoutConfig.quoteItemData = [];
                    window.checkoutConfig.totalsData = [];
                }
            ).catch();
        }
    }
);
