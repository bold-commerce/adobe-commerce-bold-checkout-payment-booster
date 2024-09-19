define([
    'jquery',
], function (
    $,
) {
    'use strict';

    return {
        requestInProgress: false,
        requestQueue: [],
        synchronizedGuestData: {},
        synchronizedAddressData: {},

        post: function (path, body = {}) {
            return new Promise((resolve, reject) => {
                path = path.replace('{{configuration-group-label}}', window.checkoutConfig.bold.configurationGroupLabel);
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
        get: function (path) {
            path = path.replace('{{configuration-group-label}}', window.checkoutConfig.bold.configurationGroupLabel);
            return $.ajax({
                url: window.checkoutConfig.bold.epsUrl + path,
                type: 'GET',
                headers: {},
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
            requestData = nextRequest.body;
            $.ajax({
                url: window.checkoutConfig.bold.epsUrl + nextRequest.path,
                type: nextRequest.method,
                headers: {
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
    };
});
