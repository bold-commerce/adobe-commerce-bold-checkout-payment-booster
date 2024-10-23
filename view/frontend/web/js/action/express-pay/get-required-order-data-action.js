define(
    [
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/shipping-service',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/convert-magento-address-action'
    ],
    function (
        quote,
        checkoutData,
        shippingService,
        convertMagentoAddressAction
    ) {
        'use strict';

        /**
         * Get required order data for express pay.
         *
         * @return {Promise}
         */
        return function (requirements) {
            const payload = {};

            for (const requirement of requirements) {
                switch (requirement) {
                    case 'customer':
                        let billingAddress = quote.billingAddress();
                        const email = checkoutData.getValidatedEmailValue()
                            ? checkoutData.getValidatedEmailValue()
                            : window.checkoutConfig.customerData.email;

                        payload[requirement] = {
                            first_name: billingAddress.firstname,
                            last_name: billingAddress.lastname,
                            email_address: email,
                        };
                        break;
                    case 'items':
                        payload[requirement] = quote.getItems().map(item => ({
                            amount: parseInt(parseFloat(item.base_price) * 100),
                            label: item.name
                        }));
                        break;
                    case 'billing_address':
                        payload[requirement] = convertMagentoAddressAction(quote.billingAddress());
                        break;
                    case 'shipping_address':
                        payload[requirement] = convertMagentoAddressAction(quote.shippingAddress());
                        break;
                    case 'shipping_options':
                        payload[requirement] = shippingService.getShippingRates().map(option => ({
                            label: `${option.carrier_title} - ${option.method_title}`,
                            amount: parseFloat(option.amount) * 100,
                            id: `${option.carrier_code}_${option.method_code}`
                        }));
                        break;
                    case 'totals':
                        const totals = quote.getTotals();
                        payload[requirement] = {
                            order_total: parseFloat(totals()['grand_total'] || 0) * 100,
                            order_balance: parseFloat(totals()['grand_total'] || 0) * 100,
                            shipping_total: parseFloat(totals()['shipping_amount'] || 0) * 100,
                            discounts_total: parseFloat(totals()['discount_amount'] || 0) * 100,
                            taxes_total: parseFloat(totals()['tax'] || 0) * 100,
                        };
                        break;
                }
            }
            return payload;
        };
    }
);
