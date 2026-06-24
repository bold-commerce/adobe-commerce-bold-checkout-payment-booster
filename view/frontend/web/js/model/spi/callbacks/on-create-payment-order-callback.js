define(
    [
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/shipping-service',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-address-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-shipping-method-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/fetch-dw-shipping-rates-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/create-wallet-pay-order-action'
    ],
    function (
        quote,
        shippingService,
        updateQuoteAddressAction,
        updateQuoteShippingMethodAction,
        fetchDwShippingRatesAction,
        createWalletPayOrderAction
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
         * Callback function for creating wallet pay order.
         *
         * @param {String} paymentType
         * @param {Object} paymentPayload
         */
        return async function (paymentType, paymentPayload) {
            const paymentData = paymentPayload['payment_data'];
            const availableWalletTypes = ['apple', 'google'];
            const isWalletPayment = availableWalletTypes.includes(paymentData.payment_type);
            const addressProvided = Boolean(paymentData['shipping_address'] || paymentData['billing_address']);
            const isSpiContainer = paymentPayload.containerId === 'SPI';

            if (paymentType !== 'ppcp' && paymentType !== 'nuvei') {
                return;
            }

            if (!isSpiContainer && addressProvided) {
                const guestEmail = resolveGuestEmailFromPaymentData(paymentData);
                if (guestEmail) {
                    quote.guestEmail = guestEmail;
                }

                if (isWalletPayment) {
                    if (!paymentData['billing_address']['phoneNumber'] && paymentData['shipping_address']?.['phoneNumber']) {
                        paymentData['billing_address']['phoneNumber'] = paymentData['shipping_address']['phoneNumber'];
                    }
                    if (paymentData['shipping_address']) {
                        updateQuoteAddressAction('shipping', paymentData['shipping_address'], { skipRates: true });
                    }
                    if (paymentData['billing_address']) {
                        updateQuoteAddressAction('billing', paymentData['billing_address'], { skipRates: true });
                    }
                } else {
                    if (paymentData['shipping_address']) {
                        updateQuoteAddressAction('shipping', paymentData['shipping_address'], { skipRates: true });
                    }
                    if (paymentData['billing_address']) {
                        updateQuoteAddressAction('billing', paymentData['billing_address'], { skipRates: true });
                    }
                }

                const rates = await fetchDwShippingRatesAction();
                shippingService.setShippingRates(rates);

                // Select method locally only — do NOT POST shipping-information here.
                // PayPal createOrder runs before the shopper finalizes address/shipping in the
                // popup. Saving partial shipping data to the quote breaks Bold wallet_pay create.
                await updateQuoteShippingMethodAction(paymentData['shipping_options'], { save: false });
            }

            const walletPayResult = await createWalletPayOrderAction(paymentPayload);
            return {
                payment_data: {
                    id: walletPayResult[0]
                }
            };
        };
    }
);
