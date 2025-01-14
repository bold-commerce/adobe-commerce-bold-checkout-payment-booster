define([
    'jquery',
    'Bold_CheckoutPaymentBooster/js/action/express-pay/add-product-to-cart-action',
    'Bold_CheckoutPaymentBooster/js/action/express-pay/get-active-quote-action'
], function (
    $,
    addProductToCart,
    getActiveQuote
) {
    'use strict';
    return async function (pageSource) {
        if (pageSource !== 'product-details') {
            return;
        }

        try {
            await addProductToCart();
            let isQuoteInitialized = window.checkoutConfig.quoteData.entity_id !== '';
            if (isQuoteInitialized) {
                return;
            }

            let response = await getActiveQuote();
            response = JSON.parse(response);
            window.checkoutConfig.quoteData.entity_id = response.quoteId;
            window.checkoutConfig.quoteItemData = response.quoteItemData;
        } catch (err) {
            console.error(err);
        }
    }
});
