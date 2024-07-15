define([
    'jquery',
    'underscore',
    'mage/url',
    'mage/storage',
], function ($, _, urlBuilder, storage) {
    'use strict';

    /**
     * Magento http client.
     *
     * @type {object}
     */
    const client = {
        /**
         * Initialize client.
         *
         * @return void
         */
        initialize: function () {
            this.shopId = window.checkoutConfig.bold.paymentBooster.shopId
                ? window.checkoutConfig.bold.paymentBooster.shopId : null;
        },

        /**
         * Get data from Magento API.
         *
         * @return {Promise}
         * @param url {string}
         * @param data {object}
         */
        get: function (url, data) {
            url = url.replace('{{shopId}}', this.shopId);
            return storage.get(urlBuilder.build(url), JSON.stringify(data));
        },

        /**
         * Post data to Magento API.
         *
         * @return {Promise}
         * @param url {string}
         * @param data {object}
         */
        post: function (url, data) {
            url = url.replace('{{shopId}}', this.shopId);
            return storage.post(urlBuilder.build(url), JSON.stringify(data));
        },

        /**
         * Put data to Magento API.
         *
         * @return {Promise}
         * @param url {string}
         * @param data {object}
         */
        put: function (url, data) {
            url = url.replace('{{shopId}}', this.shopId);
            return storage.put(urlBuilder.build(url), JSON.stringify(data));
        },
    };

    client.initialize();
    return client;
});
