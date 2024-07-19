define([
    'jquery',
    'underscore',
    'mage/url',
    'mage/storage',
], function ($, _, urlBuilder, storage) {
    'use strict';

    /**
     * Magento rest http client.
     */
    return {
        /**
         * Get data from Magento API.
         *
         * @param url {string}
         * @param data {object}
         * @return {Deferred}
         */
        get: function (url, data) {
            url = url.replace('{{shopId}}', window.checkoutConfig.bold.shopId);
            return storage.get(urlBuilder.build(url), JSON.stringify(data));
        },

        /**
         * Post data to Magento API.
         *
         * @param url {string}
         * @param data {object}
         * @return {Deferred}
         */
        post: function (url, data) {
            url = url.replace('{{shopId}}', window.checkoutConfig.bold.shopId);
            return storage.post(urlBuilder.build(url), JSON.stringify(data));
        },

        /**
         * Put data to Magento API.
         *
         * @param url {string}
         * @param data {object}
         * @return {Deferred}
         */
        put: function (url, data) {
            url = url.replace('{{shopId}}', window.checkoutConfig.bold.shopId);
            return storage.put(urlBuilder.build(url), JSON.stringify(data));
        },
    };
});
