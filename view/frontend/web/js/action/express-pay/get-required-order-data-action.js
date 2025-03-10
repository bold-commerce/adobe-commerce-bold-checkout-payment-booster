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
                        const hasBillingAddress = quote.billingAddress() !== null;
                        payload[requirement] = hasBillingAddress !== null ? convertMagentoAddressAction(quote.billingAddress()) : {};
                        break;
                    case 'shipping_address':
                        const hasShippingAddress = quote.shippingAddress() !== null;
                        payload[requirement] = hasShippingAddress ? convertMagentoAddressAction(quote.shippingAddress()) : {};
                        break;
                    case 'shipping_options':
                        payload[requirement] = shippingService.getShippingRates().map(option => ({
                            label: `${option.carrier_title} - ${option.method_title}`,
                            amount: parseFloat(option.amount) * 100,
                            id: `${option.carrier_code}_${option.method_code}`,
                            is_selected: option.carrier_code === quote.shippingMethod()?.carrier_code &&
                                option.method_code === quote.shippingMethod()?.method_code
                        }));
                        break;
                    case 'totals':
                        const totals = quote.getTotals();
                        const order_balance = window.checkoutConfig.bold.isTaxIncludedInPrices ?
                            parseFloat(totals()['grand_total'] + totals()['tax_amount'] || 0) * 100 :
                            parseFloat(totals()['grand_total'] || 0) * 100;

                        payload[requirement] = {
                            order_total: parseFloat(totals()['grand_total'] || 0) * 100,
                            order_balance,
                            shipping_total: parseFloat(totals()['shipping_amount'] || 0) * 100,
                            discounts_total: parseFloat(totals()['discount_amount'] || 0) * 100,
                            fees_total: parseFloat(totals()['fee_amount'] || 0) * 100,
                            taxes_total: parseFloat(totals()['tax_amount'] || 0) * 100,
                        };
                        break;
                }
            }
            return payload;
        };
    }
);
