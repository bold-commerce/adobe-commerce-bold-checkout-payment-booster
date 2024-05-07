define(
    [],
    function () {
        'use strict';
        let countryCode = null;

        /**
         * Get customer country code.
         *
         * @return {Promise<string|null>}
         */
        return async function () {
            if (countryCode) {
                return countryCode;
            }

            const response = await fetch(window.checkoutConfig.bold.getCountryCodeUrl)
            if (response.ok) {
                countryCode = await response.text();
                countryCode = countryCode.replace(/^"|"$/g, '');
                return countryCode;
            }

            return countryCode;
        }
    }
);
