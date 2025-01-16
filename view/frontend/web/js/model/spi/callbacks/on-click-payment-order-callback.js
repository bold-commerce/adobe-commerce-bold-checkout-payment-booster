define([
    'Bold_CheckoutPaymentBooster/js/action/express-pay/add-product-to-cart-action',
    'Bold_CheckoutPaymentBooster/js/action/express-pay/get-active-quote-action'
], function (
    addProductToCart,
    getActiveQuote
) {
    'use strict';
    return async function (pageSource) {


        try {
            if (pageSource === 'product-details') {
                await addProductToCart();
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
