define([
    'uiComponent',
    'ko',
    'jquery',
    'Bold_CheckoutPaymentBooster/js/model/spi',
    'Magento_Checkout/js/model/quote',
    'checkoutData',
    'uiRegistry',
    'Bold_CheckoutPaymentBooster/js/action/get-express-pay-order-action',
    'Magento_Checkout/js/action/place-order',
    'Magento_Checkout/js/action/redirect-on-success'
], function (
    Component,
    ko,
    $,
    spi,
    quote,
    checkoutData,
    registry,
    getExpressPayOrderAction,
    placeOrderAction,
    redirectOnSuccessAction
) {
    'use strict';

    /**
     * PayPal Express pay button component.
     */
    return Component.extend({
        defaults: {
            template: 'Bold_CheckoutPaymentBooster/express-pay',
            paymentId: ko.observable(null),
            paymentApprovalData: ko.observable(null)
        },
        isVisible: ko.observable(false),
        /** @inheritdoc */

        initialize: async function () {
            this._super();

            // this._setVisibility();
            this.subscribeToSpiEvents();
            this._setVisibility();
            window.addEventListener('hashchange', this._setVisibility.bind(this));
        },
        /**
         * Set the visibility of the component.
         * @private
         */
        _setVisibility: function () {
            console.log('SET VISIBILITY');
            this.isVisible(window.location.hash === '#shipping');
            const onShippingStep = window.location.hash === '#shipping';
            // this.isVisible(window.location.hash === '#shipping' && expressPay.isEnabled());

            // On step change remove any other instance, can only have one on a page
            const ppcpExpressContainer = document.getElementById('ppcp-express-payment');
            if (ppcpExpressContainer) {
                ppcpExpressContainer.remove();
            }

            if (onShippingStep) {
                this._renderExpressPayments();
            }

            this.isVisible(onShippingStep);
        },

        _renderExpressPayments: function() {
            const containerId = 'express-pay-buttons';
            const observer = new MutationObserver(async () => {
                let boldPaymentsInstance;

                if (document.getElementById(containerId)) {
                    observer.disconnect();

                    try {
                        boldPaymentsInstance = await spi.getPaymentsClient();
                    } catch (error) {
                        console.error('Could not instantiate Bold Payments Client.', error);

                        return;
                    }

                    const allowedCountries = window.checkoutConfig.bold?.countries ?? [];
                    const walletOptions = {
                        shopName: window.checkoutConfig.bold?.shopName ?? '',
                        isPhoneRequired: window.checkoutConfig.bold?.isPhoneRequired ?? true,
                        fastlane: window.checkoutConfig.bold?.fastlane,
                        allowedCountryCodes: allowedCountries
                    };

                    boldPaymentsInstance.renderWalletPayments(containerId, walletOptions);
                }
            });
            observer.observe(document.documentElement, {
                childList: true,
                subtree: true
            });
        },

        placeOrder: async function () {
            const paymentApprovalData = this.paymentApprovalData();
            const paymentMethodData = {
                method: 'bold',
                additional_data: {
                    order_id: paymentApprovalData?.payment_data.order_id
                }
            };
            const messageContainer = registry.get('checkout.errors').messageContainer;
            let order;

            if (paymentApprovalData === null) {
                console.error('Express Pay payment data is not set.');

                return;
            }

            try {
                order = await getExpressPayOrderAction(
                    paymentApprovalData.gateway_id,
                    paymentApprovalData.payment_data.order_id
                );
            } catch (error) {
                console.error('Could not retrieve Express Pay order.', error);

                return;
            }

            quote.guestEmail = order.email;

            spi.updateAddress('shipping', this._convertAddress(order.shipping_address, order));
            spi.updateAddress('billing', this._convertAddress(order.billing_address, order));

            try {
                await spi.saveShippingInformation(true);
            } catch (error) {
                console.error('Could not save shipping information for Express Pay order.', error);

                return;
            }

            $.when(placeOrderAction(paymentMethodData, messageContainer))
                .done(
                    function () {
                        redirectOnSuccessAction.execute();
                    }
                );
        },

        /**
         * @param {Object} order
         * @param {Object} address
         * @returns {Object}
         * @private
         */
        _convertAddress: function (address, order) {
            address.first_name = order.first_name;
            address.last_name = order.last_name;
            address.state = address.province;
            address.country_code = address.country;
            address.email = order.email;

            delete address.province;
            delete address.country;

            return address;
        },
    });
});
