define(
    [
        'Bold_CheckoutPaymentBooster/js/model/insights',
    ], function (
        insights,
    ) {
        'use strict';

        /**
         * Email mixin for the PayPal insights.
         *
         * @param {Object} emailComponent - Magento_Checkout/js/view/form/element/email.
         */
        return function (MagentoEmailComponent) {
            /**
             * Adapt the email component to work with PayPal insights.
             */
            return MagentoEmailComponent.extend(
                {
                    /** @inheritdoc */
                    checkEmailAvailability: function () {
                        this._super();
                        if (this.email()) {
                            insights.submitEmail();
                        }
                    },
                }
            );
        };
    }
);
