define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'mage/storage',
        'Magento_Checkout/js/model/resource-url-manager',
        'Bold_CheckoutPaymentBooster/js/model/dw-quote-rest-url',
        'Magento_Checkout/js/model/shipping-save-processor/payload-extender',
        'Magento_Checkout/js/model/error-processor'
    ],
    function (
        $,
        quote,
        storage,
        resourceUrlManager,
        dwQuoteRestUrl,
        payloadExtender,
        errorProcessor
    ) {
        'use strict';

        /**
         * Resolve a human-readable error from a Magento REST failure response.
         *
         * @param {Object} response
         * @returns {string}
         */
        function resolveErrorMessage(response) {
            const json = response?.responseJSON;

            if (json?.parameters?.length) {
                return String(json.parameters[0]);
            }

            if (json?.message && json.message.indexOf('%') === -1) {
                return json.message;
            }

            return response?.statusText || 'Could not save shipping information for Express Pay order.';
        }

        /**
         * Save shipping information.
         *
         * Uses camelCase quote address objects — same format as Magento_Checkout
         * shipping-save-processor/default.js. Do NOT convert to snake_case REST keys.
         *
         * @param {Boolean} saveBillingAddress - Save billing address with shipping information.
         * @return {Promise}
         */
        return async function (saveBillingAddress = false) {
            const shippingAddress = quote.shippingAddress();
            const email = quote.guestEmail
                || shippingAddress?.email
                || window.checkoutConfig?.boldExpressPayCustomer?.email;

            if (email && shippingAddress && !shippingAddress.email) {
                shippingAddress.email = email;
            }

            if (shippingAddress && !shippingAddress.firstname) {
                shippingAddress.firstname = window.checkoutConfig?.boldExpressPayCustomer?.firstname || 'Guest';
            }

            if (shippingAddress && !shippingAddress.lastname) {
                shippingAddress.lastname = window.checkoutConfig?.boldExpressPayCustomer?.lastname || 'Customer';
            }

            if (shippingAddress && !shippingAddress.telephone) {
                shippingAddress.telephone = '0000000000';
            }

            if (shippingAddress?.street && !shippingAddress.street[0]) {
                shippingAddress.street[0] = 'N/A';
            }

            let billingAddress = quote.billingAddress();

            if (saveBillingAddress || !billingAddress?.countryId) {
                billingAddress = billingAddress?.countryId ? billingAddress : shippingAddress;
            }

            const payload = {
                addressInformation: {
                    shipping_address: shippingAddress,
                    shipping_method_code: quote.shippingMethod()?.method_code ?? null,
                    shipping_carrier_code: quote.shippingMethod()?.carrier_code ?? null,
                }
            };

            if (billingAddress?.countryId) {
                payload.addressInformation.billing_address = billingAddress;
            }

            if (quote.isVirtual() && shippingAddress && !shippingAddress.countryId) {
                payload.addressInformation.shipping_address.countryId =
                    payload.addressInformation.billing_address.countryId;
            }

            payloadExtender(payload);

            const serviceUrl = dwQuoteRestUrl.isDigitalWalletQuote()
                ? dwQuoteRestUrl.getSetShippingInformationUrl()
                : resourceUrlManager.getUrlForSetShippingInformation(quote);

            try {
                const response = await storage.post(
                    serviceUrl,
                    JSON.stringify(payload)
                );
                quote.setTotals(response.totals);
            } catch (response) {
                errorProcessor.process(response);
                throw new Error(resolveErrorMessage(response));
            }
        };
    }
);
