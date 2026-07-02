define(
    [
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/shipping-service',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/save-shipping-information-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/wait-for-shipping-rates-action',
        'Magento_Checkout/js/action/select-shipping-method'
    ],
    function (
        quote,
        shippingService,
        saveShippingInformation,
        waitForShippingRatesAction,
        selectShippingMethodAction
    ) {
        'use strict';

        /**
         * Resolve guest email for express pay quotes.
         *
         * @returns {string|null}
         */
        function resolveGuestEmail() {
            return quote.guestEmail
                || quote.shippingAddress()?.email
                || quote.billingAddress()?.email
                || null;
        }

        /**
         * Parse carrier/method from a PayPal option id (e.g. "flatrate_flatrate").
         *
         * @param {string} id
         * @returns {{carrier_code: string, method_code: string}|null}
         */
        function parseMethodFromOptionId(id) {
            if (!id || typeof id !== 'string') {
                return null;
            }

            const idx = id.indexOf('_');

            if (idx === -1) {
                return null;
            }

            return {
                carrier_code: id.substring(0, idx),
                method_code: id.substring(idx + 1)
            };
        }

        /**
         * Find a Magento shipping rate matching a PayPal / Bold shipping option.
         *
         * @param {Array} rates
         * @param {Object} option
         * @returns {Object|null}
         */
        function findMatchingRate(rates, option) {
            if (!option || !rates || !rates.length) {
                return null;
            }

            return rates.find(function (method) {
                const methodId = (method.carrier_code + '_' + method.method_code).replace(/\s/g, '');

                return methodId === option.id
                    || methodId === option.identifier
                    || methodId === (option.carrier_code + '_' + option.method_code);
            }) || null;
        }

        /**
         * Update quote shipping method and optionally persist via shipping-information REST.
         *
         * @param {Object|Array|null} shippingMethod
         * @param {{save?: boolean, saveBillingAddress?: boolean, waitForRates?: boolean}} options
         * @return {Promise<void>}
         */
        return async function (shippingMethod = null, options = {}) {
            const shouldSave = options.save !== false;
            const saveBillingAddress = options.saveBillingAddress === true;
            const waitForRates = options.waitForRates !== false;

            if (waitForRates) {
                await waitForShippingRatesAction({ requireRates: shouldSave });
            }

            const paypalOptions = Array.isArray(shippingMethod) && shippingMethod.length > 0
                ? shippingMethod
                : null;

            const selectedOption = paypalOptions
                ? (paypalOptions.find(function (o) { return o.is_selected; }) || paypalOptions[0])
                : shippingMethod;

            const loadedRates = shippingService.getShippingRates()();
            let newMethod = null;

            if (selectedOption) {
                newMethod = findMatchingRate(loadedRates, selectedOption);

                if (!newMethod) {
                    newMethod = parseMethodFromOptionId(selectedOption.id)
                        || parseMethodFromOptionId(selectedOption.identifier);
                }
            }

            if (!newMethod && loadedRates.length > 0) {
                newMethod = loadedRates[0];
            }

            if (!newMethod) {
                newMethod = quote.shippingMethod();
            }

            if (newMethod) {
                selectShippingMethodAction(newMethod);
            }

            if (!shouldSave) {
                return;
            }

            if (!quote.shippingMethod()?.carrier_code || !quote.shippingMethod()?.method_code) {
                throw new Error('No shipping method is available for the selected address.');
            }

            const guestEmail = resolveGuestEmail();

            if (guestEmail) {
                quote.guestEmail = guestEmail;
            }

            await saveShippingInformation(saveBillingAddress);
        };
    }
);
