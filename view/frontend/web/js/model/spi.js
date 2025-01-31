define([
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Bold_CheckoutPaymentBooster/js/model/fastlane',
    'Bold_CheckoutPaymentBooster/js/action/general/load-script-action',
    'Bold_CheckoutPaymentBooster/js/model/spi/callbacks/on-create-payment-order-callback',
    'Bold_CheckoutPaymentBooster/js/model/spi/callbacks/on-update-payment-order-callback',
    'Bold_CheckoutPaymentBooster/js/model/spi/callbacks/on-require-order-data-callback',
    'Bold_CheckoutPaymentBooster/js/model/spi/callbacks/on-approve-payment-order-callback',
    'Bold_CheckoutPaymentBooster/js/model/spi/callbacks/on-sca-payment-order-callback',
    'Magento_Ui/js/model/messageList',
    'mage/url',
    'mage/translate',
    'jquery',
], function (
    quote,
    fullScreenLoader,
    additionalValidators,
    fastlane,
    loadScriptAction,
    onCreatePaymentOrderCallback,
    onUpdatePaymentOrderCallback,
    onRequireOrderDataCallback,
    onApprovePaymentOrderCallback,
    onScaPaymentOrderCallback,
    messageList,
    urlBuilder,
    $t,
    $,
) {
    'use strict';

    let isProductPageActive = false;
    let productQuoteCreationError = null;

    const AGREEMENT_DATE_KEY = 'checkoutAcceptedAgreementDate';

    const additionalValidation = () => {
        additionalValidators.registerValidator({
            validate: function () {
                const checkboxes = Array.from(document.querySelectorAll(
                    'input[data-gdpr-checkbox-code],' +
                    '.checkout-agreement input[type="checkbox"]'
                )).filter(el => {
                    const style = window.getComputedStyle(el);
                    return style.display !== 'none' && style.visibility !== 'hidden' && el.offsetParent !== null;
                });
                if (checkboxes.length === 0) {
                    return true;
                }
                return Array
                    .from(checkboxes)
                    .every(checkbox => checkbox.checked);
            }
        });
    };
    additionalValidation();

    const validateAgreements = () => {
        if (!window.location.href.includes("#payment")) {
            return true;
        }
        if (!additionalValidators.validate()) {
            messageList.addErrorMessage({
                message: $t('Please agree to all the terms and conditions before placing the order.')
            });
            localStorage.removeItem(AGREEMENT_DATE_KEY);
            return false;
        }
        const currentTime = Date.now();
        localStorage.setItem(AGREEMENT_DATE_KEY, currentTime.toString());
        return true;
    };

    /**
     * Fastlane init model.
     *
     * @type {object}
     */
    return {
        /**
         * Load SPI SDK and initialize payments client instance.
         *
         * @returns {Promise<{}>}
         */
        getPaymentsClient: async function () {
            if (window.boldPaymentsInstance) {
                return window.boldPaymentsInstance;
            }
            if (window.createBoldPaymentsInstanceInProgress) {
                return new Promise((resolve) => {
                    const interval = setInterval(() => {
                        if (window.boldPaymentsInstance) {
                            clearInterval(interval);
                            resolve(window.boldPaymentsInstance);
                        }
                    }, 100);
                });
            }
            window.createBoldPaymentsInstanceInProgress = true;
            require.config({
                paths: {
                    bold_payments_sdk: window.checkoutConfig.bold.epsStaticUrl + '/js/payments_sdk',
                },
            });
            await new Promise((resolve, reject) => {
                require(['bold_payments_sdk'], resolve, reject);
            });
            const initialData = {
                'eps_url': window.checkoutConfig.bold.epsUrl,
                'eps_bucket_url': window.checkoutConfig.bold.epsStaticUrl,
                'group_label': window.checkoutConfig.bold.configurationGroupLabel,
                'trace_id': window.checkoutConfig.bold.publicOrderId,
                'payment_gateways': [
                    {
                        'gateway_id': Number(window.checkoutConfig.bold.gatewayId),
                        'auth_token': window.checkoutConfig.bold.epsAuthToken,
                        'currency': window.checkoutConfig.bold.currency,
                    }
                ],
                'callbacks': {
                    'onClickPaymentOrder': async (paymentType, paymentPayload) => {
                        let productAddToCartForm;
                        let productAddToCartFormData;
                        let createQuoteResponse;
                        let createQuoteResult;

                        productQuoteCreationError = null;
                        isProductPageActive = paymentPayload.containerId.includes('product-detail');

                        if (!isProductPageActive) {
                            return;
                        }

                        productAddToCartForm = document.getElementById('product_addtocart_form');

                        if (!$(productAddToCartForm).validation('isValid')) {
                            productQuoteCreationError = new Error('Invalid product form');

                            return;
                        }

                        productAddToCartFormData = new FormData(productAddToCartForm);

                        try {
                            createQuoteResponse = await fetch(
                                urlBuilder.build('bold_booster/digitalwallets_quote/create'),
                                {
                                    method: 'POST',
                                    headers: {
                                        'X_REQUESTED_WITH': 'XMLHttpRequest'
                                    },
                                    body: productAddToCartFormData,
                                    credentials: 'same-origin'
                                }
                            );
                        } catch (error) {
                            console.error('Could not create Digital Wallets product quote. Error:', error);

                            productQuoteCreationError = error;

                            return;
                        }

                        createQuoteResult = await createQuoteResponse.json();

                        console.debug({ createQuoteResult })

                        if (createQuoteResult.hasOwnProperty('error')) {
                            console.error(
                                'Could not create Digital Wallets product quote. Error:',
                                createQuoteResult.error
                            );

                            productQuoteCreationError = new Error(createQuoteResult.error);

                            return;
                        }

                        window.checkoutConfig.quoteData = createQuoteResult.quoteData;
                        window.checkoutConfig.totalsData = createQuoteResult.totalsData ?? [];
                    },
                    'onCreatePaymentOrder': async (paymentType, paymentPayload) => {
                        if (productQuoteCreationError instanceof Error) {
                            throw productQuoteCreationError;
                        }

                        if (!isProductPageActive && !validateAgreements()) {
                            throw new Error('Agreements not accepted');
                        }

                        try {
                            return await onCreatePaymentOrderCallback(paymentType, paymentPayload);
                        } catch (e) {
                            console.error(e);
                            fullScreenLoader.stopLoader();
                            throw e;
                        }
                    },
                    'onUpdatePaymentOrder': async (paymentType, paymentPayload) => {
                        if (!validateAgreements()) {
                            throw new Error('Agreements not accepted');
                        }

                        try {
                            return await onUpdatePaymentOrderCallback(paymentType, paymentPayload);
                        } catch (e) {
                            console.error(e);
                            fullScreenLoader.stopLoader();
                            throw e;
                        }
                    },
                    'onApprovePaymentOrder': async (paymentType, paymentInformation, paymentPayload) => {
                        try {
                            return await onApprovePaymentOrderCallback(paymentType, paymentInformation, paymentPayload);
                        } catch (e) {
                            console.error(e);
                            fullScreenLoader.stopLoader();
                            throw e;
                        }
                    },
                    'onScaPaymentOrder': async function (paymentType, paymentPayload) {
                        try {
                            return await onScaPaymentOrderCallback(paymentType, paymentPayload);
                        } catch (e) {
                            console.error(e);
                            fullScreenLoader.stopLoader();
                            throw e;
                        }
                    },
                    'onRequireOrderData': function (requirements) {
                        try {
                            return onRequireOrderDataCallback(requirements);
                        } catch (e) {
                            console.error(e);
                            fullScreenLoader.stopLoader();
                            throw e;
                        }
                    },
                    'onErrorPaymentOrder': function (errors) {
                        if (isProductPageActive && window.quoteData?.hasOwnProperty('entity_id')) {
                            debugger
                            const deactivateQuoteFormData = new FormData();

                            deactivateQuoteFormData.append('form_key', $.mage.cookies.get('form_key'));
                            deactivateQuoteFormData.append('quote_id', window.quoteData.entity_id);

                            fetch(
                                urlBuilder.build('bold_booster/digitalwallets_quote/deactivate'),
                                {
                                    method: 'POST',
                                    headers: {
                                        'X_REQUESTED_WITH': 'XMLHttpRequest'
                                    },
                                    body: deactivateQuoteFormData,
                                    credentials: 'same-origin'
                                }
                            ).then(
                                response => {
                                    const result = response.json();

                                    if (result.success) {
                                        return;
                                    }

                                    console.error(result.error);
                                }
                            ).then(() => { window.quoteData = []; })
                            .catch();
                        }

                        console.error('An unexpected PayPal error occurred', errors);
                        messageList.addErrorMessage({ message: 'Warning: An unexpected error occurred. Please try again.' });
                    }
                }
            };
            const paymentsInstance = new window.bold.Payments(initialData);
            window.boldFastlaneInstance = await fastlane.getFastlaneInstance(paymentsInstance);
            await paymentsInstance.initialize;
            if (paymentsInstance.paymentGateways[0]?.type === 'braintree') {
                await this._loadBraintreeScripts(paymentsInstance); //todo: remove as soon as payments.js is adapted to use requirejs
            }
            window.boldPaymentsInstance = paymentsInstance;
            window.createBoldPaymentsInstanceInProgress = false;
            return window.boldPaymentsInstance;
        },
        /**
         * Make sure Fastlane is initialized before payments instance is created.
         *
         * @return {Promise<*>}
         */
        getFastlaneInstance: async function () {
            if (window.boldFastlaneInstance) {
                return window.boldFastlaneInstance;
            }
            await this.getPaymentsClient();
            return window.boldFastlaneInstance;
        },
        /**
         * Load Braintree scripts via require js.
         *
         * @return {Promise<void>}
         * @private
         */
        _loadBraintreeScripts: async function (paymentsInstance) {
            await loadScriptAction('bold_braintree_client', 'braintree.client');
            await loadScriptAction('bold_braintree_data_collector', 'braintree.dataCollector');
            const gatewayData = paymentsInstance.paymentGateways[0].credentials || null;
            if (!gatewayData) {
                return;
            }
            if (gatewayData.is_paypal_enabled) {
                await loadScriptAction('bold_braintree_paypal_checkout', 'braintree.paypalCheckout');
            }
            if (gatewayData.is_google_pay_enabled) {
                await loadScriptAction('bold_braintree_google_payment', 'braintree.googlePayment');
                await loadScriptAction('bold_google_pay');
            }
            if (gatewayData.is_apple_pay_enabled) {
                await loadScriptAction('bold_apple_pay', 'braintree.applePay');
            }
        },
    };
});
