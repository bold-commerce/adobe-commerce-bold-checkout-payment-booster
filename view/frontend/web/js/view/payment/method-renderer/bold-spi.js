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
    'Bold_CheckoutPaymentBooster/js/action/general/hydrate-order-action',
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
    hydrateOrderAction,
    messageList,
    additionalValidators
) {
    'use strict';

    const AGREEMENT_VALIDITY_DURATION = 5 * 60 * 1000;
    const AGREEMENT_DATE_KEY = 'checkoutAcceptedAgreementDate';

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
                if (isVisible) {
                    this.subscribeToSpiEvents();
                    this.initPaymentForm();
                    this.removeFullScreenLoaderOnError();
                }
            });
            this.isVisible(window.checkoutConfig.bold?.paymentBooster);
            const delayedHydrateOrder = _.debounce(
                async function () {
                    try {
                        await hydrateOrderAction();
                    } catch (e) {
                        console.error(e);
                        this.isVisible(false);
                    }
                }.bind(this),
                500
            );
            hydrateOrderAction().then(() => {
                quote.billingAddress.subscribe(function () {
                    delayedHydrateOrder();
                }, this);
            }).catch((reason) => {
                console.error(reason);
                this.isVisible(false);
            });
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
                document.querySelectorAll('input[data-gdpr-checkbox-code="privacy_checkbox"],' +
                    '.checkout-agreement input[type="checkbox"]').forEach(checkbox => {
                    checkbox.checked = true;
                });
            }

            if (isFastlaneAvailable) {
                const fastlaneOptions = {
                    fastlane: isFastlaneAvailable,
                    shouldRenderSpiFrame: false,
                    shouldRenderPaypalButton: true,
                    shouldRenderAppleGoogleButtons: true,
                    shopName: window.checkoutConfig.bold?.shopName ?? '',
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
                shouldRenderPaypalButton: true,
                shouldRenderAppleGoogleButtons: true,
            }
            paymentsInstance.renderPayments('SPI', paymentOptions);

            if (window?.boldPaymentsInstance?.state?.paypal?.ppcpCredentials?.credentials?.standard_payments) {
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
                    email: email,
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
                            messageList.addErrorMessage({message: $t('Payment failed. Please try again or select a different payment method in the "Pay With" section.')});
                        }
                    case 'EVENT_SPI_TOKENIZE_FAILED':
                        this.paymentId(null);
                        console.log('Failed to tokenize');
                        fullscreenLoader.stopLoader();
                        this.isSpiLoading(false);
                        break;
                    case 'EVENT_SPI_PAYMENT_ORDER_SCA':
                        fullscreenLoader.stopLoader();
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
