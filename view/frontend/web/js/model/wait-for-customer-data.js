define([], function () {
    'use strict';

    /**
     * Wait until Magento_Customer/js/customer-data is initialized before POST requests
     * that trigger customer-data section invalidation.
     *
     * Uses a dynamic require() so the factory parameter is never named "customerData",
     * which can collide with checkoutConfig.boldExpressPayCustomer in bundled builds.
     *
     * @returns {Promise<void>}
     */
    return function () {
        return new Promise(function (resolve) {
            require(['Magento_Customer/js/customer-data'], function (magentoCustomerSection) {
                if (typeof magentoCustomerSection.getInitCustomerData === 'function') {
                    magentoCustomerSection.getInitCustomerData().done(resolve).fail(resolve);
                    return;
                }

                if (typeof magentoCustomerSection.get === 'function') {
                    resolve();
                    return;
                }

                resolve();
            });
        });
    };
});
