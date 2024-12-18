define([
    'mage/url',
], function (
    urlBuilder
) {
    'use strict';

    /**
     * Get Checkout Configuration
     *
     * @returns {Deferred}
     */
    return function () {
        return fetch(
            urlBuilder.build('rest/V1/cart/getCheckoutConfig'),
            { method: 'GET' }
        );
    };
});
