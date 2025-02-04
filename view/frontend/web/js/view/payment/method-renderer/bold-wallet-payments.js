define(
    [
        'Magento_Checkout/js/view/payment/default',
        'ko',
        'Bold_CheckoutPaymentBooster/js/model/spi',
    ], function (
        DefaultPaymentComponent,
        ko,
        spi,
    ) {
        'use strict';
        return DefaultPaymentComponent.extend({
            defaults: {
                template: 'Bold_CheckoutPaymentBooster/payment/wallet-payments',
                paymentId: ko.observable(null),
                isVisible: ko.observable(false),
                isWalletPayLoading: ko.observable(false),
            },
            /** @inheritdoc */
            initialize: function () {
                this._super(); //call Magento_Checkout/js/view/payment/default::initialize()
                this.isVisible.subscribe((isVisible) => {
                    if (isVisible) {
                        this.initPaymentButtons();
                    }
                });
                this.isVisible(window.checkoutConfig.bold?.paymentBooster);
            },
            /**
             * Initialize Payment Buttons.
             *
             * @return {Promise<void>}
             */
            initPaymentButtons: async function () {
                const observer = new MutationObserver(function () {
                    if (document.getElementById('wallet-payments')) {
                        observer.disconnect();
                        this.renderPaymentsButtons();
                    }
                }.bind(this));
                observer.observe(document.body, {
                    childList: true,
                    subtree: true,
                });
            },
            /**
             * Load Payment Buttons.
             *
             * @returns {Promise<void>}
             */
            renderPaymentsButtons: async function () {
                this.isWalletPayLoading(true);
                const boldPaymentsInstance = await spi.getPaymentsClient();
                const gatewayData = boldPaymentsInstance.paymentGateways[0] || null;
                const isBraintreeWalletPayments = gatewayData.type === 'braintree' && (gatewayData.credentials.is_paypal_enabled || gatewayData.credentials.is_google_pay_enabled || gatewayData.credentials.is_paypal_enabled);
                const isPaymentsButtonsVisible = gatewayData && (gatewayData.type === 'ppcp' || isBraintreeWalletPayments);
                if (isPaymentsButtonsVisible) {
                    const walletPaymentOptions = {
                        shopName: window.checkoutConfig.bold?.shopName ?? '',
                        isPhoneRequired: window.checkoutConfig.bold?.isPhoneRequired ?? true,
                        fastlane: false,
                        pageSource: 'checkout',
                        updateShipping: false
                    }
                    boldPaymentsInstance.renderWalletPayments('wallet-payments', walletPaymentOptions);
                    this.isWalletPayLoading(false);
                } else {
                    this.isWalletPayLoading(false);
                    this.isVisible(false);
                }
            }
        });
    }
);
