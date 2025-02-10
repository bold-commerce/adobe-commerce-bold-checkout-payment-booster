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

        return async function (paymentApprovalData, isSpiContainer) {
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
                let phone = address.phone || order.billing_address.telephone || order.billing_address.phone;

                address.first_name = order.first_name;
                address.last_name = order.last_name;
                address.state = address.province;
                address.country_code = address.country;
                address.telephone = phone;

                if (!address.email && order.email) {
                    address.email = order.email;
                }

                delete address.province;
                delete address.country;
                delete address.phone;

                return address;
            }

            if (isSpiContainer) {
                updateQuoteAddressAction('billing', _convertAddress(order.billing_address, order));
            } else {
                quote.guestEmail = order.email;
                updateQuoteAddressAction('billing', _convertAddress(order.billing_address, order));

                if (paymentApprovalData.shipping_strategy === 'dynamic') {
                    updateQuoteAddressAction('shipping', _convertAddress(order.shipping_address, order));
                }
            }
        };
    }
);
