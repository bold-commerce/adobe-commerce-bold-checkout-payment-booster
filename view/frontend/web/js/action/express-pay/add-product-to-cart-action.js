define([
    'jquery'
], function (
    $
) {
    'use strict';
    return function () {
        const productAddToCartForm = document.getElementById('product_addtocart_form');

        const productAddToCartUrl = productAddToCartForm.getAttribute('action');
        const addToCartFormData = new FormData(productAddToCartForm);
        addToCartFormData.append('source', 'expresspay');

        if (!($("#product_addtocart_form").validation('isValid'))) {
            throw new Error('Product form invalid');
        }

        try {
            return fetch(productAddToCartUrl, {
                method: "POST",
                headers: {},
                body: addToCartFormData,
                credentials: 'same-origin'
            });
        } catch (err) {
            console.error(err);
        }
    }
});
