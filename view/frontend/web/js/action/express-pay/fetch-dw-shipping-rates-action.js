define([
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'mage/storage',
    'Magento_Checkout/js/model/resource-url-manager',
    'Bold_CheckoutPaymentBooster/js/model/dw-quote-rest-url'
], function (
    quote,
    shippingService,
    storage,
    resourceUrlManager,
    dwQuoteRestUrl
) {
    'use strict';

    /**
     * Estimate shipping methods for the current quote.
     *
     * Digital wallet quotes always use guest-carts/:maskedId, even for logged-in customers.
     *
     * @returns {Promise<Array>}
     */
    return async function () {
        const estimateUrl = dwQuoteRestUrl.isDigitalWalletQuote()
            ? dwQuoteRestUrl.getEstimateShippingMethodsUrl()
            : resourceUrlManager.getUrlForEstimationShippingMethodsForNewAddress(quote);

        const addressPayload = dwQuoteRestUrl.quoteAddressToRestPayload(quote.shippingAddress());

        if (!estimateUrl || !addressPayload) {
            return [];
        }

        shippingService.isLoading(true);
        try {
            const rates = await storage.post(
                estimateUrl,
                JSON.stringify({ address: addressPayload })
            );

            return Array.isArray(rates) ? rates : [];
        } catch (e) {
            console.error('Could not estimate shipping methods for Express Pay quote.', e);
            return [];
        } finally {
            shippingService.isLoading(false);
        }
    };
});
