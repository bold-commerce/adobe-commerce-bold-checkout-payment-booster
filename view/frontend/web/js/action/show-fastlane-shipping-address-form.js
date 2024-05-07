define(
    [
        'Bold_CheckoutPaymentBooster/js/model/fastlane',
        'Bold_CheckoutPaymentBooster/js/action/set-quote-shipping-address'
    ], function (
        fastlane,
        setQuoteShippingAddressAction
    ) {
        'use strict';

        /**
         * Show fastlane address modal form instead of Magento modal form and set selected address.
         *
         * {@inheritdoc}
         */
        return function () {
            /**
             * Show fastlane shipping address modal form.
             *
             * @return {Promise<{selectionChanged: boolean, selectedAddress: {}}>}
             * @private
             */
            const showFastlaneAddressModal = async function () {
                const fastlaneInstance = await fastlane.getFastlaneInstance();
                if (!fastlaneInstance) {
                    return {selectionChanged: false, selectedAddress: {}};
                }
                return fastlaneInstance.profile.showShippingAddressSelector();
            }
            showFastlaneAddressModal().then(editAddressResult => {
                if (!editAddressResult.selectionChanged) {
                    return;
                }
                setQuoteShippingAddressAction(editAddressResult.selectedAddress);
            });
        }
    });
