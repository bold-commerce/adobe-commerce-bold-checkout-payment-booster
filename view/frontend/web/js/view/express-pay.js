define([
    'uiComponent',
    'Bold_CheckoutPaymentBooster/js/model/express-pay',
    'Magento_Ui/js/model/messageList',
    'ko',
    'mage/translate'
], function (
    Component,
    expressPay,
    messageList,
    ko,
    $t
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
        initialize: async function () {
            this._super();

            await expressPay.loadExpressGatewayData();
            this._setVisibility();
            window.addEventListener('hashchange', this._setVisibility.bind(this));

            expressPay.loadPPCPSdk().then(() => {
                // Button rendering & styles will be taken over by SPI SDK
                let buttonStyles = {};
                buttonStyles['layout'] = 'horizontal';
                buttonStyles['tagline'] = false;

                const observer = new MutationObserver(() => {
                    const element = document.getElementById('express-pay-buttons');
                    if (element) {
                        observer.disconnect();
                        window.paypal.Buttons({
                            style: buttonStyles,
                            async createOrder() {
                                const response = await expressPay.createOrder();

                                if (response !== undefined) {
                                    return response[0];
                                } else {
                                    messageList.addErrorMessage({ message: $t('An error occurred while processing your payment. Please try again.') });
                                }
                            },
                            async onShippingAddressChange(data, actions) {
                                expressPay.updateQuoteShippingAddress(data['shippingAddress']);

                                try {
                                    await expressPay.updateOrder(data['orderID']);
                                } catch (e) {
                                    return actions.reject(data.errors.ADDRESS_ERROR);
                                }
                            },
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
