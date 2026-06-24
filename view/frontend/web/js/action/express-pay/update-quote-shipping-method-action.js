define(
    [
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/shipping-service',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/save-shipping-information-action',
        'Magento_Checkout/js/action/select-shipping-method'
    ],
    function (
        quote,
        shippingService,
        saveShippingInformation,
        selectShippingMethodAction
    ) {
        'use strict';

        /**
         * Resolve guest email for digital wallet / express pay quotes.
         *
         * @returns {string|null}
         */
        function resolveGuestEmail() {
            return quote.guestEmail
                || quote.shippingAddress()?.email
                || quote.billingAddress()?.email
                || window.checkoutConfig?.boldExpressPayCustomer?.email
                || null;
        }

        /**
         * Parse a shipping method stub from a Bold/PayPal option ID string.
         * IDs follow the pattern "carrier_code_method_code" (e.g. "flatrate_flatrate").
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
         * Find a Magento shipping rate matching a Bold/PayPal shipping option.
         *
         * @param {Array}  rates   Magento shipping rates
         * @param {Object} option  Bold/PayPal shipping option
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
         * Update quote shipping method and optionally save shipping information.
         *
         * Called from two contexts:
         *   - onUpdatePaymentOrder (address change): options.save = false — no REST save
         *     because PayPal provides only a partial address at this point.
         *   - onCreatePaymentOrder / other flows: options.save = true (default) — saves
         *     shipping information to Magento REST API.
         *
         * @param {Object|Array|null} shippingMethod  Shipping option(s) or null
         * @param {{save?: boolean}}  options          { save: false } to skip REST save
         * @return {Promise<void>}
         */
        return async function (shippingMethod = null, options = {}) {
            const shouldSave = options.save !== false;

            const paypalOptions = Array.isArray(shippingMethod) && shippingMethod.length > 0
                ? shippingMethod
                : null;

            const selectedOption = paypalOptions
                ? (paypalOptions.find(function (o) { return o.is_selected; }) || paypalOptions[0])
                : shippingMethod;

            // shippingService.getShippingRates() returns a Knockout observableArray (a function).
            // Invoke it with () to get the underlying plain JavaScript array so that
            // Array methods (.find, .length, indexing) work correctly.
            const loadedRates = shippingService.getShippingRates()();
            let newMethod = null;

            if (selectedOption) {
                // 1st: match against Magento's loaded rates (exact carrier+method match)
                newMethod = findMatchingRate(loadedRates, selectedOption);

                // 2nd: parse carrier/method directly from the option ID (e.g. "flatrate_flatrate")
                if (!newMethod) {
                    newMethod = parseMethodFromOptionId(selectedOption.id)
                        || parseMethodFromOptionId(selectedOption.identifier);
                }
            }

            // When no matching option was found, pick the first available loaded rate.
            // For the update-address callback, fetchRatesForDWQuote() is called before
            // this action so loadedRates should already be populated.
            if (!newMethod && loadedRates.length > 0) {
                newMethod = loadedRates[0];
            }

            // Last resort: keep whatever is already on the quote
            if (!newMethod) {
                newMethod = quote.shippingMethod();
            }

            if (newMethod) {
                selectShippingMethodAction(newMethod);
            }

            if (!shouldSave) {
                return;
            }

            // Only save to the REST API when we have a valid method
            if (!quote.shippingMethod()?.carrier_code || !quote.shippingMethod()?.method_code) {
                throw new Error('No shipping method is available for the selected address.');
            }

            const guestEmail = resolveGuestEmail();
            if (guestEmail) {
                quote.guestEmail = guestEmail;
            }

            await saveShippingInformation();
        };
    }
);
