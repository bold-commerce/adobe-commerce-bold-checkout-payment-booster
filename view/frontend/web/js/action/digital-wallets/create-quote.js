define(
    [
        'jquery',
        'mage/url',
        'Magento_Checkout/js/model/quote',
    ],
    function ($, urlBuilder, quote) {
        'use strict';

        /**
         * @returns {Promise<void>}
         * @throws Error
         */
        return async function () {
            let productAddToCartForm;
            let productAddToCartFormData;
            let createQuoteResponse;
            let createQuoteResult;

            productAddToCartForm = document.getElementById('product_addtocart_form');

            if (!$(productAddToCartForm).validation('isValid')) {
                throw new Error('Invalid product form');
            }

            productAddToCartFormData = new FormData(productAddToCartForm);

            productAddToCartFormData.append('bold_order_id', window.checkoutConfig.bold.publicOrderId ?? '');

            try {
                createQuoteResponse = await fetch(
                    urlBuilder.build('bold_booster/digitalwallets_quote/create'),
                    {
                        method: 'POST',
                        headers: {
                            'X_REQUESTED_WITH': 'XMLHttpRequest'
                        },
                        body: productAddToCartFormData,
                        credentials: 'same-origin'
                    }
                );
            } catch (error) {
                console.error('Could not create Digital Wallets product quote. Error:', error);

                throw error;
            }

            createQuoteResult = await createQuoteResponse.json();

            if (createQuoteResult.hasOwnProperty('error')) {
                console.error(
                    'Could not create Digital Wallets product quote. Error:',
                    createQuoteResult.error
                );

                throw new Error(createQuoteResult.error);
            }

            // Set values at property level instead of overwriting entire object to preserve its reference in memory.
            Object.keys(createQuoteResult.quoteData).forEach(function (key) {
                window.checkoutConfig.quoteData[key] = createQuoteResult.quoteData[key];
            });

            window.checkoutConfig.quoteItemData = createQuoteResult.quoteItemData;

            quote.setTotals(createQuoteResult.totalsData);
        };
    }
);
