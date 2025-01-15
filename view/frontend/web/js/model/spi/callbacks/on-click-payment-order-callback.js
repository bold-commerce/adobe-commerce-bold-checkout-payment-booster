define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Bold_CheckoutPaymentBooster/js/action/express-pay/add-product-to-cart-action',
    'Bold_CheckoutPaymentBooster/js/action/express-pay/get-active-quote-action'
], function (
    $,
    quote,
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
            Object.assign(window.checkoutConfig, response.checkoutConfig);
        } catch (err) {
            console.error(err);
        }
    }
});
