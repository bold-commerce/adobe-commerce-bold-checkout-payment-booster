define(
    [
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-address-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-shipping-method-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/create-wallet-pay-order-action',
        'Magento_Checkout/js/model/quote'
    ],
    function (
        updateQuoteAddressAction,
        updateQuoteShippingMethodAction,
        createWalletPayOrderAction,
        quote
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
                        updateQuoteAddressAction('shipping', paymentData['shipping_address']);
                    }
                    if (paymentData['billing_address']) {
                        updateQuoteAddressAction('billing', paymentData['billing_address']);
                    }
                } else {
                    if (paymentData['shipping_address']) {
                        updateQuoteAddressAction('shipping', paymentData['shipping_address']);
                    }
                    if (paymentData['billing_address']) {
                        updateQuoteAddressAction('billing', paymentData['billing_address']);
                    }
                }

                // Select method locally only — do NOT POST shipping-information during createOrder.
                // PayPal createOrder runs before the shopper finalizes address/shipping in the popup.
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
