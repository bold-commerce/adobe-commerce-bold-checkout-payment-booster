define([
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/url-builder'
], function (quote, urlBuilder) {
    'use strict';

    /**
     * @returns {string|null}
     */
    function getQuoteMaskId() {
        return window.checkoutConfig?.quoteData?.entity_id || quote.getQuoteId() || null;
    }

    /**
     * Digital wallet quotes on PDP / mini-cart are always guest carts (masked ID),
     * even when the shopper is logged in.
     *
     * @returns {boolean}
     */
    function isDigitalWalletQuote() {
        const quoteMaskId = getQuoteMaskId();

        if (!quoteMaskId) {
            return false;
        }

        // DW quotes use a 32-char masked guest-cart ID (not a numeric quote entity ID).
        if (String(quoteMaskId).length === 32 && !/^\d+$/.test(String(quoteMaskId))) {
            return true;
        }

        return Boolean(
            window.checkoutConfig?.quoteData?.is_digital_wallets
            || window.checkoutConfig?.quoteData?.extension_attributes?.bold_order_id
        );
    }

    /**
     * @returns {string|null}
     */
    function getEstimateShippingMethodsUrl() {
        const quoteMaskId = getQuoteMaskId();

        if (!quoteMaskId) {
            return null;
        }

        return urlBuilder.createUrl('/guest-carts/:quoteId/estimate-shipping-methods', {
            quoteId: quoteMaskId
        });
    }

    /**
     * @returns {string|null}
     */
    function getSetShippingInformationUrl() {
        const quoteMaskId = getQuoteMaskId();

        if (!quoteMaskId) {
            return null;
        }

        return urlBuilder.createUrl('/guest-carts/:cartId/shipping-information', {
            cartId: quoteMaskId
        });
    }

    /**
     * Convert a Magento quote address to the snake_case REST payload Magento expects.
     *
     * @param {Object} address
     * @returns {Object|null}
     */
    function quoteAddressToRestPayload(address) {
        if (!address || !address.countryId) {
            return null;
        }

        const region = address.region;
        let regionId = address.regionId || null;
        let regionName = null;

        if (typeof region === 'object' && region !== null) {
            regionId = region.region_id || regionId;
            regionName = region.region || region.region_code || null;
        } else if (region) {
            regionName = region;
        }

        let street = Array.isArray(address.street)
            ? address.street.filter(function (line) { return line; })
            : (address.street ? [address.street] : []);

        if (!street.length) {
            street = ['N/A'];
        }

        return {
            country_id: address.countryId,
            region_id: regionId,
            region: regionName || '',
            postcode: address.postcode || '',
            city: address.city || '',
            street: street,
            firstname: address.firstname || 'Guest',
            lastname: address.lastname || 'Customer',
            telephone: address.telephone || '',
            email: address.email || quote.guestEmail || window.checkoutConfig?.boldExpressPayCustomer?.email || ''
        };
    }

    return {
        isDigitalWalletQuote: isDigitalWalletQuote,
        getEstimateShippingMethodsUrl: getEstimateShippingMethodsUrl,
        getSetShippingInformationUrl: getSetShippingInformationUrl,
        quoteAddressToRestPayload: quoteAddressToRestPayload
    };
});
