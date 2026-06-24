define(
    [
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/shipping-service',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-address-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-shipping-method-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/fetch-dw-shipping-rates-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-wallet-pay-order-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/get-required-order-data-action'
    ],
    function (
        quote,
        shippingService,
        updateQuoteAddressAction,
        updateQuoteShippingMethodAction,
        fetchDwShippingRatesAction,
        updateWalletPayOrderAction,
        getRequiredOrderDataAction
    ) {
        'use strict';

        /**
         * Resolve guest email from PayPal / wallet payment payload.
         *
         * @param {Object} paymentData
         * @returns {string|null}
         */
        function resolveGuestEmailFromPaymentData(paymentData) {
            const sa = paymentData?.shipping_address;
            const ba = paymentData?.billing_address;

            return sa?.email
                || sa?.emailAddress
                || paymentData?.email
                || paymentData?.customer?.email_address
                || paymentData?.customer?.payer_email
                || ba?.email
                || quote.guestEmail
                || window.checkoutConfig?.boldExpressPayCustomer?.email
                || null;
        }

        /**
         * Whether the quote address has enough data to persist via shipping-information.
         *
         * @returns {boolean}
         */
        function isShippingAddressReadyForSave() {
            const address = quote.shippingAddress();

            return Boolean(
                address
                && address.countryId
                && address.city
                && address.postcode
            );
        }

        /**
         * Callback function for updating payment order (PayPal address / shipping change).
         *
         * @param {String} paymentType
         * @param {Object} paymentPayload
         */
        return async function (paymentType, paymentPayload) {
            const paymentData = paymentPayload['payment_data'];
            const availableWalletTypes = ['apple', 'google'];
            const isWalletPayment = availableWalletTypes.includes(paymentData.payment_type);

            const guestEmail = resolveGuestEmailFromPaymentData(paymentData);
            if (guestEmail) {
                quote.guestEmail = guestEmail;
                if (paymentData.shipping_address && !paymentData.shipping_address.email) {
                    paymentData.shipping_address.email = guestEmail;
                }
            }

            if (paymentData['shipping_address']) {
                updateQuoteAddressAction('shipping', paymentData['shipping_address'], { skipRates: true });
            }

            const rates = await fetchDwShippingRatesAction();
            shippingService.setShippingRates(rates);

            // Persist address + method to the DW guest cart when the address is complete enough.
            // updateWalletPayOrderAction reads the quote from the DB via QuoteConverter — without
            // this save, shipping_options in the Bold PATCH are empty and PayPal shows no selector.
            const shouldSave = isShippingAddressReadyForSave() && rates.length > 0;

            try {
                await updateQuoteShippingMethodAction(paymentData['shipping_options'], { save: shouldSave });
            } catch (error) {
                console.error('Could not save shipping information during Express Pay address update.', error);

                if (shouldSave) {
                    await updateQuoteShippingMethodAction(paymentData['shipping_options'], { save: false });
                } else {
                    throw error;
                }
            }

            if (paymentType === 'ppcp' && !isWalletPayment) {
                await updateWalletPayOrderAction(paymentData['order_id'], paymentPayload['gateway_id']);
            }

            const requirements = paymentPayload['require_order_data'] || [];

            if (rates.length > 0) {
                if (!requirements.includes('shipping_options')) {
                    requirements.push('shipping_options');
                }
                if (!requirements.includes('totals')) {
                    requirements.push('totals');
                }
            }

            return getRequiredOrderDataAction(requirements);
        };
    }
);
