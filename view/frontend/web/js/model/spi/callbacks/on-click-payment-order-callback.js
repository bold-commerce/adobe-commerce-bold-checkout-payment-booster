define(['jquery'], function ($) {
    'use strict';
    return async function (pageSource) {
        if (pageSource !== 'product') {
            return;
        }

        const productAddToCartForm = document.getElementById('product_addtocart_form');

        const productAddToCartUrl = productAddToCartForm.getAttribute('action');
        const addToCartFormData = new FormData(productAddToCartForm);
        addToCartFormData.append('source', 'expresspay');

        if (!($("#product_addtocart_form").validation('isValid'))) {
            throw new Error('Product form invalid');
        }

        try {
            await fetch(productAddToCartUrl, {
                method: "POST",
                body: addToCartFormData
            });
        } catch (err) {
            console.log(err);
        }
    }
});
