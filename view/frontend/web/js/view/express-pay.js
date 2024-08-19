define([
    'uiComponent',
    'Bold_CheckoutPaymentBooster/js/model/express-pay',
    'Magento_Customer/js/model/customer',
    'ko'
], function (
    Component,
    expressPay,
    customer,
    ko
) {
    'use strict';

    /**
     * PayPal Express pay button component.
     */
    return Component.extend({
        defaults: {
            template: 'Bold_CheckoutPaymentBooster/express-pay'
        },
        isVisible: ko.observable(false),
        initialize: function () {
            this._super();
            this._setVisibility();

            window.addEventListener('hashchange', this._setVisibility.bind(this));

            expressPay.loadPPCPSdk().then(() => {
                let buttonStyles = expressPay.getStyles();
                buttonStyles['layout'] = 'horizontal';
                buttonStyles['tagline'] = false;

                const observer = new MutationObserver(() => {
                    const element = document.getElementById('express-pay-buttons');
                    if (element) {
                        observer.disconnect();
                        window.paypal.Buttons({
                            style: buttonStyles
                        }).render(element);
                    }
                });
                observer.observe(document.documentElement, {
                    childList: true,
                    subtree: true
                });
            });
        },
        /**
         * Set the visibility of the component.
         * @private
         */
        _setVisibility: function () {
            this.isVisible(window.location.hash === '#shipping' && expressPay.isEnabled());
        }
    });
});
