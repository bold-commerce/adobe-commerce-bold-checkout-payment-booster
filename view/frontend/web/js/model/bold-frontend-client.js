define([
    'jquery',
    'underscore',
    'Bold_CheckoutPaymentBooster/js/model/address',
    'Bold_CheckoutPaymentBooster/js/model/customer',
], function (
    $,
    _,
    boldAddress,
    boldCustomer,
) {
    'use strict';

    return {
        requestInProgress: false,
        requestQueue: [],
        synchronizedGuestData: {},
        synchronizedAddressData: {},

        post: function (path, body = {}) {
            return new Promise((resolve, reject) => {
                this.requestQueue.push({
                    resolve: resolve,
                    reject: reject,
                    path: path,
                    body: body,
                    method: 'POST',
                });
                this.processNextRequest();
            });
        },
        put: function (path, body = {}) {
            return new Promise((resolve, reject) => {
                this.requestQueue.push({
                    resolve: resolve,
                    reject: reject,
                    path: path,
                    body: body,
                    method: 'PUT',
                });
                this.processNextRequest();
            });
        },
        get: function (path) {
            return $.ajax({
                url: window.checkoutConfig.bold.url + path,
                type: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + window.checkoutConfig.bold.jwtToken,
                    'Content-Type': 'application/json',
                },
            });
        },
        delete: function (path, payload = {}) {
            return $.ajax({
                url: window.checkoutConfig.bold.url + path,
                type: 'DELETE',
                headers: {
                    'Authorization': 'Bearer ' + window.checkoutConfig.bold.jwtToken,
                    'Content-Type': 'application/json',
                },
                data: JSON.stringify(payload),
            });
        },
        /**
         * Process next request in the queue.
         *
         * @return {*}
         */
        processNextRequest: function () {
            if (this.requestInProgress || this.requestQueue.length === 0) {
                return;
            }
            this.requestInProgress = true;
            const nextRequest = this.requestQueue.shift();
            let requestData;
            let skipRequest = false;

            switch (nextRequest.path) {
                case 'addresses/billing':
                    try {
                        requestData = boldAddress.getAddress();
                    } catch (e) {
                        requestData = null;
                    }
                    if (!requestData || _.isEqual(requestData, this.synchronizedAddressData)) {
                        skipRequest = true;
                    }
                    break;
                case 'customer/guest':
                    try {
                        requestData = boldCustomer.getCustomer();
                    } catch (e) {
                        requestData = null;
                    }
                    if (!requestData || _.isEqual(requestData, this.synchronizedGuestData)) {
                        skipRequest = true;
                    }
                    break;
                default:
                    requestData = nextRequest.body;
                    break;
            }

            if (skipRequest) {
                nextRequest.resolve();
                this.requestInProgress = false;
                return this.processNextRequest();
            }
            $.ajax({
                url: window.checkoutConfig.bold.url + nextRequest.path,
                type: nextRequest.method,
                headers: {
                    'Authorization': 'Bearer ' + window.checkoutConfig.bold.jwtToken,
                    'Content-Type': 'application/json',
                },
                data: JSON.stringify(requestData),
            }).done(function (result) {
                nextRequest.resolve(result);
                this.requestInProgress = false;
                switch (nextRequest.path) {
                    case 'addresses/billing':
                        this.synchronizedAddressData = requestData;
                        break;
                    case 'customer/guest':
                        this.synchronizedGuestData = requestData;
                        break;
                    default:
                        break;
                }
                this.processNextRequest();
            }.bind(this)).fail(function (error) {
                nextRequest.reject(error);
                this.requestInProgress = false;
                this.processNextRequest();
            }.bind(this));
        },
    };
});
