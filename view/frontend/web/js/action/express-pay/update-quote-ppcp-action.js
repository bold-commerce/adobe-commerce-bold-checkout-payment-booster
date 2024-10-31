define(
    [
        'uiRegistry',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/redirect-on-success',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/get-express-pay-order-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-address-action',
    ],
    function (
        registry,
        $,
        quote,
        placeOrderAction,
        redirectOnSuccessAction,
        getExpressPayOrderAction,
        updateQuoteAddressAction,
    ) {
        'use strict';

        /**
         * Place order action.
         *
         * @return {Promise}
         */
        return async function (paymentApprovalData) {
            const paymentData = paymentApprovalData['payment_data'];
            const availableWalletTypes = ['apple', 'google'];
            const isWalletPayment = availableWalletTypes.includes(paymentData.payment_type);

            let order;
            try {
                order = await getExpressPayOrderAction(
                    paymentApprovalData.gateway_id,
                    paymentApprovalData.payment_data.order_id
                );
            } catch (error) {
                console.error('Could not retrieve Express Pay order.', error);

                return;
            }
            const _convertAddress = function (address, order) {
                address.first_name = order.first_name;
                address.last_name = order.last_name;
                address.state = address.province;
                address.country_code = address.country;

                if (!address.email && order.email) {
                    address.email = order.email;
                }

                delete address.province;
                delete address.country;

                return address;
            }

            if (!isWalletPayment) {
                quote.guestEmail = order.email;
                updateQuoteAddressAction('shipping', _convertAddress(order.shipping_address, order));
                updateQuoteAddressAction('billing', _convertAddress(order.billing_address, order));
            }
        };
    }
);
