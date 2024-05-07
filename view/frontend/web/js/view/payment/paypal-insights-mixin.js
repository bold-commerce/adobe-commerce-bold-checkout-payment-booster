define(
    [
        'Bold_CheckoutPaymentBooster/js/model/insights',
    ], function (
        insights,
    ) {
        'use strict';

        /**
         * Default payment mixin for PayPal insights.
         *
         * @param {Object} MagentoDefaultPaymentComponent - Magento_Checkout/js/view/payment/default.
         */
        return function (MagentoDefaultPaymentComponent) {
            /**
             * Send checkout ends event.
             */
            return MagentoDefaultPaymentComponent.extend(
                {
                    /** @inheritdoc */
                    afterPlaceOrder: function () {
                        this._super();
                        insights.endCheckout();
                    },
                    /** @inheritdoc */
                    selectPaymentMethod: function () {
                        this._super();
                        if (this.item.method === 'bold' || this.item.method === 'bold_fastlane') {
                            return true;
                        }
                        insights.selectPaymentMethod(this.item.method);
                        return true;
                    }
                }
            );
        };
    }
);
