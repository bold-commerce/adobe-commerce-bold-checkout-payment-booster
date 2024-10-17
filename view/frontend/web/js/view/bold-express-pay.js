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
    'Magento_Checkout/js/action/redirect-on-success',
    'Bold_CheckoutPaymentBooster/js/action/render-wallet-payments-action',
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
    redirectOnSuccessAction,
    renderWalletPaymentsAction
) {
    'use strict';

    /**
     * PayPal Express pay button component.
     */
    return Component.extend({
        defaults: {
            template: 'Bold_CheckoutPaymentBooster/express-pay',
            paymentId: ko.observable(null),
            containerId: 'spi-express-pay-buttons',
            paymentApprovalData: ko.observable(null),
        },
        isVisible: ko.observable(true),

        /** @inheritdoc */
        initialize: async function () {
            this._super();
            this.renderWalletPaymentsOnInit();
            this.trackRenderWalletPayments();
        },
        trackRenderWalletPayments: function () {
            registry.async('checkout.steps.billing-step.payment')((paymentComponent) => {
                if (paymentComponent && paymentComponent.isVisible) {
                    paymentComponent.isVisible.subscribe(async function (isPaymentsVisible) {
                        this.isVisible(!isPaymentsVisible);
                        if (!isPaymentsVisible) {
                            try {
                                await renderWalletPaymentsAction(this.containerId);
                            } catch (e) {
                                this.isVisible(false);
                                console.error('Could not render wallet payments.', e);
                            }
                        }
                    }.bind(this));
                }
            });
        },
        renderWalletPaymentsOnInit: function () {
            const observer = new MutationObserver(async function () {
                if (document.getElementById(this.containerId)) {
                    observer.disconnect();
                    try {
                        await renderWalletPaymentsAction(this.containerId);
                    } catch (e) {
                        this.isVisible(false);
                        console.error('Could not render wallet payments.', e);
                    }
                }
            }.bind(this));
            observer.observe(document.body, {childList: true, subtree: true});
        },
    });
});
