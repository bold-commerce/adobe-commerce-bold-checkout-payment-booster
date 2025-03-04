define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/shipping-service',
        'Bold_CheckoutPaymentBooster/js/action/express-pay/convert-magento-address-action'
    ],
    function (
        $,
        quote,
        checkoutData,
        shippingService,
        convertMagentoAddressAction
    ) {
        'use strict';

        /**
         * @returns {[{label: string, amount: number}]}
         */
        function getProductItemData() {
            let $priceElement;
            let productPrice;

            $priceElement = $('.bundle-info .price-box .price');

            if ($priceElement.length === 0) {
                $priceElement = $('.product-info-main .price-box .price');
            }

            // Get price for simple, configurable, bundle, virtual and downloadable products
            if ($priceElement.length === 1) {
                productPrice = parseInt($priceElement[0].innerText.replace(/\D/g, ''));
            }

            // Calculate price for grouped products
            if ($priceElement.length > 1) {
                productPrice = $priceElement
                    .toArray()
                    .reduce(
                        (totalPrice, currentPriceElement) => {
                            const quantity = parseInt(
                                $(currentPriceElement)
                                    .closest('.item')
                                    .siblings('.qty')
                                    .find('input.qty')
                                    .val()
                                || 0
                            );

                            return totalPrice + parseInt(currentPriceElement.innerText.replace(/\D/g, '') * quantity);
                        },
                        0
                    );
            }

            return [
                {
                    label: $('.page-title').text().trim(),
                    amount: productPrice
                }
            ];
        }

        function getProductTotalsData() {
            const productTotal = getProductItemData().pop()?.amount ?? 0;

            return {
                order_total: productTotal,
                order_balance: productTotal,
                shipping_total: 0,
                discounts_total: 0,
                fees_total: 0,
                taxes_total: 0,
            }

        }

        function getQuoteTotalsData() {
            const totals = quote.getTotals();
            const order_balance = window.checkoutConfig.bold.isTaxIncludedInPrices ?
            parseFloat(totals()['grand_total'] + totals()['tax_amount'] || 0) * 100 :
            parseFloat(totals()['grand_total'] || 0) * 100;

            return {
                order_total: parseFloat(totals()['grand_total'] || 0) * 100,
                order_balance,
                shipping_total: parseFloat(totals()['shipping_amount'] || 0) * 100,
                discounts_total: parseFloat(totals()['discount_amount'] || 0) * 100,
                fees_total: parseFloat(totals()['fee_amount'] || 0) * 100,
                taxes_total: parseFloat(totals()['tax_amount'] || 0) * 100,
            };
        }

        /**
         * Get required order data for express pay.
         *
         * @return {Object}
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
                        let quoteItems = [];

                        if (window.checkoutConfig?.hasOwnProperty('quoteItemData')) {
                            quoteItems = quote.getItems().map(item => ({
                                amount: parseInt(parseFloat(item.base_price) * 100),
                                label: item.name
                            }));
                        }

                        if ($('body').hasClass('catalog-product-view') && quoteItems.length === 0) {
                            quoteItems = getProductItemData();
                        }

                        payload[requirement] = quoteItems;

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
                        // if on product page and active quote is not bold quote
                        if ($('body').hasClass('catalog-product-view') && !window.checkoutConfig.quoteData?.extension_attributes?.bold_order_id) {
                            payload[requirement] = getProductTotalsData();
                        } else {
                            payload[requirement] = getQuoteTotalsData();
                        }
                        break;
                }
            }
            return payload;
        };
    }
);
