define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/action/select-billing-address',
        'Bold_CheckoutPaymentBooster/js/model/bold-frontend-client',
        'Bold_CheckoutPaymentBooster/js/model/fastlane',
        'Bold_CheckoutPaymentBooster/js/action/convert-fastlane-address',
        'Magento_Checkout/js/model/quote',
        'checkoutData',
        'Magento_Checkout/js/model/full-screen-loader',
        'uiRegistry',
        'underscore',
        'ko',
        'mage/translate',
    ], function (
        MagentoPayment,
        errorProcessor,
        selectPaymentMethodAction,
        selectBillingAddressAction,
        boldFrontendClient,
        fastlane,
        convertFastlaneAddressAction,
        quote,
        checkoutData,
        loader,
        registry,
        _,
        ko,
        $t,
    ) {
        'use strict';
        return MagentoPayment.extend({
            defaults: {
                template: 'Bold_CheckoutPaymentBooster/payment/fastlane',
                paymentContainer: '#bold-fastlane-payment-container',
                isVisible: ko.observable(true),
                fastlanePaymentComponent: null,
                error: $t('An error occurred while processing your payment. Please try again.'),
            },

            /**
             * @inheritDoc
             */
            initialize: function () {
                this._super();
                if (!fastlane.isEnabled()) {
                    this.isVisible(false);
                    return;
                }
                this.renderPaymentContainer();
                if (fastlane.memberAuthenticated()) {
                    this.selectPaymentMethod();
                }
                fastlane.memberAuthenticated.subscribe(function (authenticated) {
                    if (authenticated === true) {
                        this.selectPaymentMethod();
                    }
                    this.renderPaymentContainer();
                }, this);
                quote.shippingAddress.subscribe(function () {
                    const shippingAddress = this.getFastlaneShippingAddress();
                    if (shippingAddress && this.fastlanePaymentComponent) {
                        this.fastlanePaymentComponent.updatePrefills(
                            {
                                phoneNumber: this.getFormattedPhoneNumber(),
                            },
                        );
                        this.fastlanePaymentComponent.setShippingAddress(shippingAddress);
                    }
                }, this);
            },
            /**
             * Wait for the payment container to be rendered before rendering the Fastlane component.
             *
             * This is necessary because the payment container is rendered asynchronously.
             *
             * @returns {void}
             */
            renderPaymentContainer: function () {
                const observer = new MutationObserver(function () {
                    const addressFilled = quote.isVirtual() || (quote.shippingAddress().firstname && quote.shippingAddress().lastname);
                    if (addressFilled && document.querySelector(this.paymentContainer)) {
                        observer.disconnect();
                        this.renderCardComponent();
                    }
                }.bind(this));
                observer.observe(document.body, {
                    childList: true,
                    subtree: true,
                });
            },
            /**
             * Render the Fastlane component in the payment container.
             *
             * @return {Promise<void>}
             */
            renderCardComponent: async function () {
                try {
                    const fastlaneInstance = await fastlane.getFastlaneInstance();
                    if (!fastlaneInstance) {
                        this.isVisible(false);
                        return;
                    }
                    const fields = {
                        phoneNumber: {
                            prefill: this.getFormattedPhoneNumber(),
                        },
                    };
                    const styles = window.checkoutConfig.bold.fastlane.styles.length > 0
                        ? window.checkoutConfig.bold.fastlane.styles
                        : {};
                    this.fastlanePaymentComponent = await fastlaneInstance.FastlanePaymentComponent(
                        {
                            styles,
                            fields,
                        },
                    );
                    const shippingAddress = this.getFastlaneShippingAddress();
                    if (shippingAddress) {
                        this.fastlanePaymentComponent.setShippingAddress(shippingAddress);
                    }
                    this.fastlanePaymentComponent.render(this.paymentContainer);
                    this.isPlaceOrderActionAllowed(true);
                } catch (e) {
                    this.isPlaceOrderActionAllowed(false);
                    this.isVisible(false);
                }
            },
            /**
             * @inheritDoc
             */
            placeOrder: function (data, event) {
                loader.startLoader();
                const placeMagentoOrder = this._super.bind(this);
                this.processBoldOrder().then(() => {
                    const orderPlacementResult = placeMagentoOrder(data, event);
                    loader.stopLoader();
                    return orderPlacementResult;
                }).catch((error) => {
                    let errorMessage;
                    try {
                        errorMessage = error.responseJSON && error.responseJSON.errors
                            ? error.responseJSON.errors[0].message
                            : error.message;
                    } catch (e) {
                        errorMessage = this.error;
                    }
                    loader.stopLoader();
                    errorProcessor.process({responseText: JSON.stringify({message: errorMessage})}, this.messageContainer);
                    return false;
                });

            },
            /**
             * Process order on Bold side before Magento order placement.
             *
             * @return {Promise<*>}
             */
            processBoldOrder: async function () {
                try {
                    if (fastlane.getType() === 'braintree') {
                        await this.processBraintreePayment();
                        return;
                    }
                    await this.processPPCPPayment();
                } catch (e) {
                    return Promise.reject(e);
                }
            },
            /**
             * Process payment for the Braintree gateway.
             *
             * @return {Promise<never>}
             */
            processBraintreePayment: async function () {
                const tokenResponse = await this.fastlanePaymentComponent.getPaymentToken();
                this.updateQuoteBillingAddress(tokenResponse);
                await this.sendGuestCustomerInfo();
                await boldFrontendClient.get('refresh');
                await boldFrontendClient.post('taxes');
                const addPaymentsResult = await boldFrontendClient.post(
                    'payments',
                    {
                        'gateway_public_id': fastlane.getGatewayPublicId(),
                        'currency': quote.totals().quote_currency_code,
                        'token': tokenResponse.id,
                        'type': 'fastlane',
                    },
                );
                if (addPaymentsResult.errors) {
                    return Promise.reject('An error occurred while processing your payment. Please try again.');
                }
            },
            /**
             * Process payment for the PPCP gateway.
             *
             * @return {Promise<never>}
             */
            processPPCPPayment: async function () {
                const tokenResponse = await this.fastlanePaymentComponent.getPaymentToken();
                this.updateQuoteBillingAddress(tokenResponse);
                await this.sendGuestCustomerInfo();
                await boldFrontendClient.get('refresh');
                await boldFrontendClient.post('taxes');
                const walletPayResult = await boldFrontendClient.post(
                    'wallet_pay/create_order',
                    {
                        gateway_type: 'paypal',
                        payment_data: {
                            locale: navigator.language,
                            payment_type: 'fastlane',
                            token: tokenResponse.id,
                        },
                    },
                );
                if (walletPayResult.errors) {
                    return Promise.reject('An error occurred while processing your payment. Please try again.');
                }
                const addPaymentResult = await boldFrontendClient.post(
                    'payments',
                    {
                        'gateway_public_id': fastlane.getGatewayPublicId(),
                        'currency': quote.totals().quote_currency_code,
                        'token': walletPayResult.data?.payment_data?.id,
                    },
                );
                if (addPaymentResult.errors) {
                    return Promise.reject('An error occurred while processing your payment. Please try again.');
                }
            },
            /**
             * Update quote billing address with the one from the payment token response.
             *
             * @param {{paymentSource: {card: {billingAddress}}}}tokenResponse
             */
            updateQuoteBillingAddress: function (tokenResponse) {
                const fastlaneBillingAddress = tokenResponse.paymentSource && tokenResponse.paymentSource.card && tokenResponse.paymentSource.card.billingAddress
                    ? tokenResponse.paymentSource.card.billingAddress
                    : null;
                if (!fastlaneBillingAddress) {
                    return;
                }
                let quoteAddress = quote.isVirtual() ? quote.billingAddress() : quote.shippingAddress();
                if (!quoteAddress) {
                    quoteAddress = {
                        firstname: null,
                        lastname: null,
                        telephone: null,
                    };
                }
                let fastlaneFirstName;
                try {
                    fastlaneFirstName = fastlane.profileData() && fastlane.profileData().name.firstName
                        ? fastlane.profileData().name.firstName
                        : tokenResponse.paymentSource.card.name.split(' ')[0];
                } catch (e) {
                    fastlaneFirstName = quoteAddress.firstname;
                }
                if (!fastlaneFirstName) {
                    throw new Error('First name is missing.');
                }
                let fastlaneLastName;
                try {
                    fastlaneLastName = fastlane.profileData() && fastlane.profileData().name.lastName
                        ? fastlane.profileData().name.lastName
                        : tokenResponse.paymentSource.card.name.split(' ')[1];
                } catch (e) {
                    fastlaneLastName = quoteAddress.lastname;
                }
                if (!fastlaneLastName) {
                    fastlaneLastName = fastlaneFirstName;
                }
                fastlaneBillingAddress.firstName = fastlaneFirstName;
                fastlaneBillingAddress.lastName = fastlaneLastName;
                if (!fastlaneBillingAddress.phoneNumber) {
                    fastlaneBillingAddress.phoneNumber = quoteAddress.telephone;
                }
                const billingAddress = convertFastlaneAddressAction(fastlaneBillingAddress, 'braintree');
                selectBillingAddressAction(billingAddress);
            },
            /**
             * Get Fastlane shipping address for the payment component.
             *
             * @return {object|null}
             * @private
             */
            getFastlaneShippingAddress: function () {
                if (quote.isVirtual()) {
                    return null;
                }
                const quoteAddress = quote.shippingAddress();
                if (!quoteAddress) {
                    return null;
                }
                return {
                    firstName: quoteAddress.firstname,
                    lastName: quoteAddress.lastname,
                    streetAddress: quoteAddress.street[0],
                    extendedAddress: quoteAddress.street[1],
                    locality: quoteAddress.city,
                    region: quoteAddress.regionCode,
                    postalCode: quoteAddress.postcode,
                    countryCodeAlpha2: quoteAddress.countryId,
                    phoneNumber: quoteAddress.telephone,
                };
            },
            /**
             * Send guest customer info to Bold.
             *
             * @private
             */
            sendGuestCustomerInfo: async function () {
                try {
                    await boldFrontendClient.post('customer/guest');
                } catch (error) {
                    let errorMessage;
                    try {
                        errorMessage = error.responseJSON && error.responseJSON.errors
                            ? error.responseJSON.errors[0].message
                            : error.message;
                    } catch (e) {
                        errorMessage = this.error;
                    }
                    errorProcessor.process(errorMessage, this.messageContainer);
                }
            },
            /**
             * Remove country code from the Fastlane phone number.
             *
             * @return {string}
             */
            getFormattedPhoneNumber: function () {
                let phoneNumber = '';
                if (quote.isVirtual() && quote.billingAddress()) {
                    phoneNumber = quote.billingAddress().telephone || '';
                }
                if (!phoneNumber && quote.shippingAddress()) {
                    phoneNumber = quote.shippingAddress().telephone || '';
                }
                phoneNumber = phoneNumber.replace(/\D/g, '');
                if (!phoneNumber) {
                    return '';
                }
                if (phoneNumber.length === 11 && phoneNumber.startsWith('1')) {
                    return phoneNumber.substring(1);
                }
                return phoneNumber;
            },
        });
    });
