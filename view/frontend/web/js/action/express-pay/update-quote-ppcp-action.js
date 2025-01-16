define(
    [
        'Magento_Checkout/js/model/quote',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/get-express-pay-order-action',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/update-quote-address-action',
    ],
    function (
        quote,
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
            let order;
            try {
                order = await getExpressPayOrderAction(
                    paymentApprovalData.gateway_id,
                    paymentApprovalData.payment_data.order_id ?? paymentApprovalData.order_id
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

            if (paymentApprovalData.shipping_strategy !== 'fixed') {
                quote.guestEmail = order.email;
                updateQuoteAddressAction('shipping', _convertAddress(order.shipping_address, order));
            }
            updateQuoteAddressAction('billing', _convertAddress(order.billing_address, order));
        };
    }
);
