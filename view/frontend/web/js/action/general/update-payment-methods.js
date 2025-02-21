define([
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/action/get-payment-information',
    'Magento_Checkout/js/model/checkout-data-resolver',
    'jquery'
], function(quote, checkoutData, getPaymentInformation, checkoutDataResolver, $) {
    'use strict';
    return function() {
        quote.setPaymentMethod(null);
        checkoutData.setSelectedPaymentMethod(null);
        checkoutDataResolver.resolvePaymentMethod();
        var deferred = $.Deferred();
        getPaymentInformation(deferred);
    };
});
