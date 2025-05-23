define([
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/action/select-shipping-address',
    'Magento_Checkout/js/action/get-totals',
    'checkoutData',
    'underscore',
    'ko',
    'mage/translate',
    'Bold_CheckoutPaymentBooster/js/model/spi',
    'Bold_CheckoutPaymentBooster/js/model/platform-client',
    'Bold_CheckoutPaymentBooster/js/model/fastlane',
    'Magento_Ui/js/model/messageList',
    'Magento_Checkout/js/model/payment/additional-validators'
], function (
    DefaultPaymentComponent,
    quote,
    fullscreenLoader,
    customerData,
    selectShippingAddressAction,
    getTotalsAction,
    checkoutData,
    _,
    ko,
    $t,
    spi,
    platformClient,
    fastlane,
    messageList,
    additionalValidators
) {
    'use strict';

    const AGREEMENT_VALIDITY_DURATION = 5 * 60 * 1000;
    const AGREEMENT_DATE_KEY = 'checkoutAcceptedAgreementDate';
    const PAYMENT_FAILED_MESSAGE = 'Payment failed. Please try again or select a different payment method';

    const validateAgreements = () => {
        if ((!window.location.href.includes("#payment")) &&
            (!window.location.href.includes("#shipping"))) {
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

    const removeOldAgreementDate = () => {
        const acceptedAgreementDate = localStorage.getItem(AGREEMENT_DATE_KEY);
        const currentTime = Date.now();
        if (acceptedAgreementDate) {
            const elapsedTime = currentTime - parseInt(acceptedAgreementDate, 10);
            if (elapsedTime > AGREEMENT_VALIDITY_DURATION) {
                localStorage.removeItem(AGREEMENT_DATE_KEY);
            }
        }
    };
    removeOldAgreementDate();

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

    return DefaultPaymentComponent.extend({
        defaults: {
            template: 'Bold_CheckoutPaymentBooster/payment/spi',
            paymentId: ko.observable(null),
            paymentApprovalData: ko.observable(null),
            isVisible: ko.observable(false),
            isSpiLoading: ko.observable(true),
            isBillingAddressRequired: ko.observable(true),
            isPlaceOrderButtonVisible: ko.observable(true),
        },

        /** @inheritdoc */
        initialize: function () {
            this._super(); //call Magento_Checkout/js/view/payment/default::initialize()
            this.isVisible.subscribe((isVisible) => {
                if (isVisible && typeof window.boldSpiRendered === 'undefined') {
                    window.boldSpiRendered = true;
                    this.subscribeToSpiEvents();
                    this.initPaymentForm();
                    this.removeFullScreenLoaderOnError();
                }
            });
            this.isVisible(window.checkoutConfig.bold?.paymentBooster);
        },
        /**
         * Initialize SPI payment form.
         *
         * @return {Promise<void>}
         */
        initPaymentForm: async function () {
            const observer = new MutationObserver(function () {
                if (document.getElementById('SPI')) {
                    observer.disconnect();
                    this.renderPayments();
                }
            }.bind(this));
            observer.observe(document.body, {
                childList: true,
                subtree: true,
            });
        },
        /**
         * Load SPI SDK.
         *
         * @returns {Promise<void>}
         */
        renderPayments: async function () {
            const paymentsInstance = await spi.getPaymentsClient();
            const boldPaymentsForm = document.getElementById('SPI');
            const isFastlaneAvailable = fastlane.isAvailable();
            this.isSpiLoading(false);

            if (localStorage.getItem(AGREEMENT_DATE_KEY)) {
                document.querySelectorAll('input[data-gdpr-checkbox-code],' +
                    '.checkout-agreement input[type="checkbox"]').forEach(checkbox => {
                    checkbox.checked = true;
                });
            }

            if (isFastlaneAvailable) {
                const fastlaneOptions = {
                    fastlane: isFastlaneAvailable,
                    shouldRenderSpiFrame: false,
                    shouldRenderPaypalButton: false,
                    shouldRenderAppleGoogleButtons: false,
                    shopName: window.checkoutConfig.bold?.shopName ?? '',
                    allowedCountries: window.checkoutConfig.bold?.countries ?? null,
                };
                paymentsInstance.renderPayments('SPI', fastlaneOptions);
                this.isBillingAddressRequired(false);
                this.isPlaceOrderButtonVisible(false);
                if (boldPaymentsForm.getHTML().trim() === '') {
                    this.isVisible(false);
                }
                return;
            }
            this.isBillingAddressRequired(true);
            this.isPlaceOrderButtonVisible(true);

            const paymentOptions = {
                fastlane: false,
                shouldRenderSpiFrame: true,
                shouldRenderPaypalButton: false,
                shouldRenderAppleGoogleButtons: false,
                allowedCountries: window.checkoutConfig.bold?.countries ?? null,
            }
            paymentsInstance.renderPayments('SPI', paymentOptions);
            if (boldPaymentsForm.getHTML().trim() === '') {
                this.isVisible(false);
            }

            const isPlaceOrderButtonNotVisible = paymentsInstance.paymentGateways?.every((paymentGateway) => paymentGateway.gateway_services.credit_card_form === false);
            if (isPlaceOrderButtonNotVisible) {
                this.isPlaceOrderButtonVisible(false);
            }
        },

        /** @inheritdoc */
        placeOrder: function (data, event) {
            const placeMagentoOrder = this._super.bind(this);
            if (this.paymentId()) {
                return placeMagentoOrder(data, event);
            }
            this.tokenize();
            return false;
        },

        /**
         * Show full-screen loader and process the order.
         *
         * @return boolean
         */
        placeOrderClick: function (data, event) {
            this.paymentId(null);

            if (!validateAgreements()) {
                throw new Error('Agreements not accepted');
            }

            const iframe = document.querySelector('iframe[title="paypal_card_form"]');
            const iframeContent = iframe?.contentWindow?.document;
            const submitButton = iframeContent?.getElementById('submit-button')
                ?.innerHTML?.indexOf('Pay ') > -1;

            if (submitButton) {
                iframeContent.getElementById('submit-button').click();
                return;
            }

            fullscreenLoader.startLoader();
            return this.placeOrder(data, event);
        },

        /**
         * Remove full-screen loader in case place order returns error from backend.
         *
         * @private
         * @returns {void}
         */
        removeFullScreenLoaderOnError: function () {
            this.messageContainer.errorMessages.subscribe(function (errorMessages) {
                if (errorMessages.length > 0) {
                    fullscreenLoader.stopLoader();
                }
            });
        },

        /**
         * Send tokenize action to SPI iframe.
         *
         * @return void
         */
        tokenize: function () {
            const iframeWindow = document.getElementById('spi_frame_SPI')?.contentWindow;
            if (!iframeWindow) {
                fullscreenLoader.stopLoader();
                messageList.addErrorMessage({message: $t(PAYMENT_FAILED_MESSAGE)});
                return;
            }
            const billingAddress = quote.billingAddress();
            const shippingAddress = quote.isVirtual() ? quote.billingAddress() : quote.shippingAddress();
            const email = checkoutData.getValidatedEmailValue()
                ? checkoutData.getValidatedEmailValue()
                : window.checkoutConfig.customerData.email;
            const payload = {
                customer: {
                    first_name: billingAddress.firstname,
                    last_name: billingAddress.lastname,
                    email_address: email,
                },
                billing_address: {
                    first_name: billingAddress.firstname,
                    last_name: billingAddress.lastname,
                    address_line_1: billingAddress.street[0],
                    address_line_2: billingAddress.street[1],
                    province_code: billingAddress.region,
                    city: billingAddress.city,
                    postal_code: billingAddress.postcode,
                    country_code: billingAddress.countryId,
                },
                shipping_address: {
                    first_name: shippingAddress.firstname,
                    last_name: shippingAddress.lastname,
                    address_line_1: shippingAddress.street[0],
                    address_line_2: shippingAddress.street[1],
                    province_code: shippingAddress.region,
                    city: shippingAddress.city,
                    postal_code: shippingAddress.postcode,
                    country_code: shippingAddress.countryId,
                },
                totals: {
                    order_total: quote.totals()['base_grand_total'] * 100,
                    shipping_total: quote.totals()['base_shipping_amount'] * 100,
                    discounts_total: quote.totals()['base_discount_amount'] * 100,
                    taxes_total: quote.totals()['base_tax_amount'] * 100,
                },
            };
            iframeWindow.postMessage({actionType: 'ACTION_SPI_TOKENIZE', payload: payload}, '*');
        },
        /**
         * Subscribe to SPI iframe events.
         *
         * @returns {void}
         */
        subscribeToSpiEvents() {
            window.addEventListener('message', ({data}) => {
                const eventType = data?.eventType;
                switch (eventType) {
                    case 'EVENT_SPI_INITIALIZED':
                        this.isSpiLoading(false);
                        break;
                    case 'EVENT_SPI_TOKENIZED':
                        const paymentId = data.payload?.payload?.data?.payment_id;
                        if (paymentId) {
                            this.paymentId(paymentId);

                            const placeOrderSuccess = this.placeOrder({}, jQuery.Event());
                            if (!placeOrderSuccess) {
                                fullscreenLoader.stopLoader();
                            }
                            return;
                        }
                        if (paymentId === undefined && data.payload?.success === false) {
                            // Error message for empty or invalid CC details, temporary fix until CHK-7079 is resolved
                            messageList.addErrorMessage({message: $t(PAYMENT_FAILED_MESSAGE)});
                            fullscreenLoader.stopLoader();
                        }
                        break;
                    case 'EVENT_SPI_TOKENIZE_FAILED':
                        this.paymentId(null);
                        this.isSpiLoading(false);
                        fullscreenLoader.stopLoader();
                        console.log('Failed to tokenize');
                        break;
                    case 'EVENT_SPI_ENABLE_FULLSCREEN':
                        fullscreenLoader.stopLoader();
                        break;
                    case 'EVENT_SPI_DISABLE_FULLSCREEN':
                        fullscreenLoader.startLoader();
                        break;
                }
            });
        },
    });
});
