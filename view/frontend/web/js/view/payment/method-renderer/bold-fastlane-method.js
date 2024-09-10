define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Checkout/js/action/select-billing-address',
        'Magento_Checkout/js/action/set-billing-address',
        'Bold_CheckoutPaymentBooster/js/model/bold-frontend-client',
        'Bold_CheckoutPaymentBooster/js/model/fastlane',
        'Bold_CheckoutPaymentBooster/js/action/convert-fastlane-address',
        'Magento_Checkout/js/model/quote',
        'checkoutData',
        'Magento_Checkout/js/model/full-screen-loader',
        'uiRegistry',
        'underscore',
        'ko'
    ], function (
        MagentoPayment,
        errorProcessor,
        selectPaymentMethodAction,
        selectBillingAddressAction,
        setBillingAddressAction,
        boldFrontendClient,
        fastlane,
        convertFastlaneAddressAction,
        quote,
        checkoutData,
        loader,
        registry,
        _,
        ko
    ) {
        'use strict';
        return MagentoPayment.extend({
            defaults: {
                template: 'Bold_CheckoutPaymentBooster/payment/fastlane',
                paymentContainer: '#bold-fastlane-payment-container',
                isVisible: ko.observable(true),
                fastlanePaymentComponent: null,
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
                this.sendGuestCustomerInfo();
                quote.shippingAddress.subscribe(function () {
                    this.sendGuestCustomerInfo();
                    if (window.checkoutConfig.bold.fastlane.memberAuthenticated !== true
                        && checkoutData.getSelectedPaymentMethod() === 'bold_fastlane') {
                        selectPaymentMethodAction(null);
                        checkoutData.setSelectedPaymentMethod(null);
                    }
                }, this);
                this.renderPaymentContainer();
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
                        if (window.checkoutConfig.bold.fastlane.memberAuthenticated === true) {
                            this.selectPaymentMethod();
                        }
                    }
                }.bind(this));
                observer.observe(document.body, {
                    childList: true,
                    subtree: true
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
                    const quoteAddress = quote.isVirtual() ? quote.billingAddress() : quote.shippingAddress();
                    let telephone = null;
                    if (quoteAddress) {
                        telephone = quoteAddress.telephone;
                    }
                    const fields = {
                        phoneNumber: {
                            prefill: telephone
                        }
                    };
                    const styles = window.checkoutConfig.bold.fastlane.styles || {};
                    const shippingAddress = this.getFastlaneShippingAddress();
                    this.fastlanePaymentComponent = await fastlaneInstance.FastlanePaymentComponent(
                        {
                            styles,
                            fields
                        }
                    );
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
            selectPaymentMethod: function () {
                this.renderCardComponent();
                return this._super();
            },
            /**
             * @inheritDoc
             */
            placeOrder: function (data, event) {
                loader.startLoader();
                window.postMessage(
                    {
                        responseType: 'FASTLANE_ADD_PAYMENT',
                        paymentType: fastlane.getType()
                    },
                    '*'
                );
                const placeMagentoOrder = this._super.bind(this);
                this.fastlanePaymentComponent.getPaymentToken().then((tokenResponse) => {
                    this.processBoldOrder(tokenResponse).then(() => {
                        const orderPlacementResult = placeMagentoOrder(data, event);
                        loader.stopLoader()
                        return orderPlacementResult;
                    }).catch((error) => {
                        const errorMessage = error.responseJSON && error.responseJSON.errors
                            ? error.responseJSON.errors[0].message
                            : error.message;
                        loader.stopLoader();
                        errorProcessor.process(errorMessage, this.messageContainer);
                        return false;
                    });
                }).catch(() => {
                    loader.stopLoader();
                    return false;
                });
            },
            /**
             * Process order on Bold side before Magento order placement.
             *
             * @param {{paymentSource: {card: {billingAddress}}} }tokenResponse
             * @return {Promise<*>}
             */
            processBoldOrder: async function (tokenResponse) {
                try {
                    this.updateQuoteBillingAddress(tokenResponse);
                    const refreshResult = await boldFrontendClient.get('refresh');
                    const taxesResult = await boldFrontendClient.post('taxes');
                    if (fastlane.getType() === 'ppcp') {
                        const walletPayResult = await boldFrontendClient.post(
                            'wallet_pay/create_order',
                            {
                                gateway_type: 'paypal',
                                payment_data: {
                                    locale: navigator.language,
                                    payment_type: 'fastlane',
                                    token: tokenResponse.id,
                                }
                            }
                        );
                        if (walletPayResult.errors) {
                            return Promise.reject('An error occurred while processing your payment. Please try again.');
                        }
                        tokenResponse.id = walletPayResult.data?.payment_data?.id;
                    }
                    const paymentPayload = {
                        'gateway_public_id': fastlane.getGatewayPublicId(),
                        'currency': quote.totals().quote_currency_code,
                        'amount': quote.totals().grand_total * 100,
                        'token': tokenResponse.id
                    }
                    if (fastlane.getType() === 'braintree') {
                        paymentPayload.type = 'fastlane'
                    }
                    const paymentResult = await boldFrontendClient.post(
                        'payments',
                        paymentPayload
                    );
                    const orderPlacementResult = await boldFrontendClient.post('process_order');
                    if (refreshResult.errors || taxesResult.errors || paymentResult.errors || orderPlacementResult.errors) {
                        return Promise.reject('An error occurred while processing your payment. Please try again.');
                    }
                } catch (e) {
                    return Promise.reject(e);
                }
            },
            /**
             * Update quote billing address with the one from the payment token response.
             *
             * @param {{paymentSource: {card: {billingAddress}}}}tokenResponse
             */
            updateQuoteBillingAddress(tokenResponse) {
                const fastlaneBillingAddress = tokenResponse.paymentSource && tokenResponse.paymentSource.card && tokenResponse.paymentSource.card.billingAddress
                    ? tokenResponse.paymentSource.card.billingAddress
                    : null;
                if (!fastlaneBillingAddress) {
                    throw new Error('Billing address is missing in the payment token response.');
                }
                let quoteAddress = quote.isVirtual() ? quote.billingAddress() : quote.shippingAddress();
                if (!quoteAddress) {
                    quoteAddress = {
                        firstname: null,
                        lastname: null,
                        telephone: null
                    };
                }
                let fastlaneFirstName;
                try {
                    fastlaneFirstName = window.checkoutConfig.bold.fastlane.profileData && window.checkoutConfig.bold.fastlane.profileData.name.firstName
                        ? window.checkoutConfig.bold.fastlane.profileData.name.firstName
                        : tokenResponse.paymentSource.card.name.split(' ')[0];
                } catch (e) {
                    fastlaneFirstName = quoteAddress.firstname;
                }
                if (!fastlaneFirstName) {
                    throw new Error('First name is missing.');
                }
                let fastlaneLastName;
                try {
                    fastlaneLastName = window.checkoutConfig.bold.fastlane.profileData && window.checkoutConfig.bold.fastlane.profileData.name.lastName
                        ? window.checkoutConfig.bold.fastlane.profileData.name.lastName
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
                setBillingAddressAction(this.messageContainer);
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
                    phoneNumber: quoteAddress.telephone
                };
            },
            /**
             * Send guest customer info to Bold.
             *
             * @private
             */
            sendGuestCustomerInfo: async function () {
                if (window.checkoutConfig.bold.fastlane.memberAuthenticated !== true) {
                    return;
                }
                try {
                    await boldFrontendClient.post('customer/guest');
                } catch (error) {
                    const errorMessage = error.responseJSON && error.responseJSON.errors
                        ? error.responseJSON.errors[0].message
                        : error.message;
                    errorProcessor.process(errorMessage, this.messageContainer);
                }
            }
        });
    });
