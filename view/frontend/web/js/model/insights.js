define(
    [
        'Bold_CheckoutPaymentBooster/js/action/get-bold-fastlane-gateway-data',
        'Magento_Checkout/js/model/quote',
        'Bold_CheckoutPaymentBooster/js/action/get-country-code',
    ], function (
        getBoldFastlaneGatewayDataAction,
        quote,
        getCountryCodeAction,
    ) {
        'use strict';

        /**
         * Fastlane init model.
         *
         * @type {object}
         */
        return {
            beginCheckoutSent: false,
            endCheckoutSent: false,
            paymentMethod: null,

            /**
             * Check if PayPal insights is enabled and active.
             *
             * @return {*|boolean}
             * @perivate
             */
            isEnabled: function () {
                return !!window.checkoutConfig.bold.paypal_insights.enabled
            },
            /**
             * Send begin_checkout event to PayPal Insights SDK.
             *
             * @return {Promise<void>}
             */
            beginCheckout: async function () {
                if (!this.isEnabled()) {
                    return;
                }
                if (this.beginCheckoutSent) {
                    return;
                }
                const countryCode = await getCountryCodeAction();
                await new Promise((resolve) => {
                    const interval = setInterval(() => {
                        if (window.paypalInsight) {
                            clearInterval(interval);
                            resolve();
                        }
                    }, 100);
                });
                const body = {
                    session_id: window.checkoutConfig.bold.publicOrderId,
                    amount: {
                        currency: quote.totals().quote_currency_code,
                        total: quote.totals().grand_total,
                    },
                    user_data: {
                        country: countryCode,
                        is_store_member: !!window.isCustomerLoggedIn,
                    },
                    page_type: "checkout",
                };
                window.paypalInsight("event", "begin_checkout", body);
                this.beginCheckoutSent = true;
            },
            /**
             * Send end_checkout event to PayPal Insights SDK.
             *
             * @return {Promise<void>}
             */
            endCheckout: async function () {
                if (!this.isEnabled()) {
                    return;
                }
                if (this.endCheckoutSent) {
                    return;
                }
                const countryCode = await getCountryCodeAction();
                await new Promise((resolve) => {
                    const interval = setInterval(() => {
                        if (window.paypalInsight) {
                            clearInterval(interval);
                            resolve();
                        }
                    }, 100);
                });
                const body = {
                    session_id: window.checkoutConfig.bold.publicOrderId,
                    amount: {
                        currency: quote.totals().quote_currency_code,
                        total: quote.totals().grand_total,
                    },
                    payment_method_selected: this.getPaymentMethod(),
                    user_data: {
                        country: countryCode,
                        is_store_member: !!window.isCustomerLoggedIn,
                    },
                    page_type: "checkout",
                };
                window.paypalInsight("event", "end_checkout", body);
                this.endCheckoutSent = true;
            },
            /**
             * Send select_payment_method event to PayPal Insights SDK.
             *
             * @return {Promise<void>}
             */
            selectPaymentMethod: async function (code = null) {
                if (!this.isEnabled()) {
                    return;
                }
                await new Promise((resolve) => {
                    const interval = setInterval(() => {
                        if (window.paypalInsight) {
                            clearInterval(interval);
                            resolve();
                        }
                    }, 100);
                });
                const body = {
                    session_id: window.checkoutConfig.bold.publicOrderId,
                    payment_method_selected: this.getPaymentMethod(code),
                    page_type: "checkout",
                };
                window.paypalInsight("event", "select_payment_method", body);
            },
            /**
             * Send submit_checkout_email event to PayPal Insights SDK.
             *
             * @return {Promise<void>}
             */
            submitEmail: async function () {
                if (!this.isEnabled()) {
                    return;
                }
                await new Promise((resolve) => {
                    const interval = setInterval(() => {
                        if (window.paypalInsight) {
                            clearInterval(interval);
                            resolve();
                        }
                    }, 100);
                });
                const body = {
                    session_id: window.checkoutConfig.bold.publicOrderId,
                    page_type: "checkout",
                };
                window.paypalInsight("event", "submit_checkout_email", body);
            },
            /**
             * Build PayPal Insights SDK.
             *
             * @return {Promise<void>}
             */
            initInsightsSDK: async function () {
                if (!this.isEnabled()) {
                    return;
                }
                if (window.paypalInsight) {
                    return;
                }
                try {
                    const {data} = await getBoldFastlaneGatewayDataAction();
                    const script = data.is_test_mode ? 'bold_paypal_insights_sandbox' : 'bold_paypal_insights';
                    await new Promise((resolve, reject) => {
                        require([script], () => {
                            window.paypalInsightDataLayer = window.paypalInsightDataLayer || [];

                            function paypalInsight() {
                                paypalInsightDataLayer.push(arguments);
                            }

                            paypalInsight("config", data.client_id, {debug: true});
                            paypalInsight("event", "js_load", {timestamp: Date.now()});
                            resolve();
                        }, reject);
                    });
                    this.subscribeToPIGI();
                } catch (e) {
                    const message = e.responseJSON && e.responseJSON.errors[0] ? e.responseJSON.errors[0].message : e.message;
                    console.error('Failed to initialize PayPal Insights SDK.', message);
                }
            },
            /**
             * Try to detect payment method.
             *
             * @return {null}
             */
            getPaymentMethod: function (code = null) {
                if (code) {
                    this.paymentMethod = code;
                }
                const allowedPaymentMethods = [
                    'apple_pay',
                    'card',
                    'gift_certificate',
                    'google_pay',
                    'affirm',
                    'store_credit',
                    'amazon_pay',
                    'paypal',
                    'paypal_credit',
                    'venmo',
                    'other'
                ];
                if (!this.paymentMethod) {
                    this.paymentMethod = quote.paymentMethod() && quote.paymentMethod().method;
                }
                if (!this.paymentMethod || allowedPaymentMethods.indexOf(this.paymentMethod) === -1) {
                    this.paymentMethod = 'other';
                }
                return this.paymentMethod;
            },
            /**
             * Try to get payment method from PIGI iframe and Fastlane Payment Component.
             *
             * @return {void}
             */
            subscribeToPIGI: function () {
                window.addEventListener('message', ({data}) => {
                    const responseType = data.responseType;
                    if (responseType === 'PIGI_ADD_PAYMENT') {
                        switch (data.payload.paymentType) {
                            case 'CREDIT_CARD':
                                this.paymentMethod = 'card';
                                break;
                            default:
                                this.paymentMethod = data.payload.paymentType.toLowerCase();
                        }
                        this.selectPaymentMethod();
                    }
                    if (responseType === 'FASTLANE_ADD_PAYMENT') {
                        this.paymentMethod = 'paypal';
                        this.selectPaymentMethod();
                    }
                });
            }
        };
    });
