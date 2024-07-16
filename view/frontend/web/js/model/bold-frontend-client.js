define([
    'jquery',
    'underscore',
    'Bold_CheckoutPaymentBooster/js/model/address',
    'Bold_CheckoutPaymentBooster/js/model/customer'
], function (
    $,
    _,
    boldAddress,
    boldCustomer
) {
    'use strict';

    /**
     * Bold http client.
     * @type {object}
     */
    return {
        requestInProgress: false,
        requestQueue: [],
        synchronizedGuestData: {},
        synchronizedAddressData: {},

        /**
         * Post data to Bold API.
         *
         * @param path string
         * @param body object
         * @return {Promise}
         */
        post: function (path, body = {}) {
            return new Promise((resolve, reject) => {
                this.requestQueue.push({
                    resolve: resolve,
                    reject: reject,
                    path: path,
                    body: body
                });
                this.processNextRequest();
            });
        },

        /**
         * Get data from Bold API.
         *
         * @param path
         * @return {*}
         */
        get: function (path) {
            return $.ajax({
                url: window.checkoutConfig.bold.paymentBooster.url + path,
                type: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + window.checkoutConfig.bold.paymentBooster.jwtToken,
                    'Content-Type': 'application/json'
                }
            });
        },
        /**
         * Process next request in queue.
         *
         * @return void
         * @private
         */
        processNextRequest: function () {
            if (this.requestInProgress || this.requestQueue.length === 0) {
                return;
            }
            this.requestInProgress = true;
            const nextRequest = this.requestQueue.shift();
            let requestData;
            requestData = nextRequest.body;
            $.ajax({
                url: window.checkoutConfig.bold.paymentBooster.url + nextRequest.path,
                type: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + window.checkoutConfig.bold.paymentBooster.jwtToken,
                    'Content-Type': 'application/json',
                },
                data: JSON.stringify(requestData)
            }).done(function (result) {
                nextRequest.resolve(result);
                this.requestInProgress = false;
                this.processNextRequest();
            }.bind(this)).fail(function (error) {
                nextRequest.reject(error);
                this.requestInProgress = false;
                this.processNextRequest();
            }.bind(this));
        },
    }
});
