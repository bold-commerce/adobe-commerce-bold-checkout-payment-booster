define(
    [
        'uiComponent',
        'Bold_CheckoutPaymentBooster/js/model/insights',
    ],
    function (
        Component,
        insights
    ) {
        'use strict';

        return Component.extend({
            initialize: function () {
                this._super();
                insights.initInsightsSDK()
                insights.beginCheckout();
                if (window.isCustomerLoggedIn) {
                    insights.submitEmail();
                }
            }
        });
    }
);
