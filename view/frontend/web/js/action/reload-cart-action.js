define(
    [
        'checkoutData',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/model/address-converter',
        'Magento_Checkout/js/action/select-billing-address',
        'Magento_Checkout/js/action/select-shipping-address',
        'Magento_Checkout/js/action/get-totals'
    ], function (
        checkoutData,
        customerData,
        addressConverter,
        selectBillingAddressAction,
        selectShippingAddressAction,
        getTotalsAction
    ) {
        'use strict';

        /**
         * Update cart and checkout data from backend.
         *
         * @return {void}
         */
        return function () {
            customerData.reload(['bold'], false).then((cartData) => {
                const billingAddress = addressConverter.formAddressDataToQuoteAddress(cartData.bold.billingAddress);
                selectBillingAddressAction(billingAddress);
                if (cartData.bold.shippingAddress) {
                    const shippingAddress = addressConverter.formAddressDataToQuoteAddress(cartData.bold.shippingAddress);
                    selectShippingAddressAction(shippingAddress);
                    checkoutData.setSelectedShippingAddress(shippingAddress.getKey());
                }
                if (cartData.bold.shippingMethod) {
                    selectShippingMethodAction(cartData.bold.shippingMethod);
                }
                getTotalsAction([]);
            }).catch((error) => {
                console.error('Error reloading customer data', error);
            });
        };
    });
