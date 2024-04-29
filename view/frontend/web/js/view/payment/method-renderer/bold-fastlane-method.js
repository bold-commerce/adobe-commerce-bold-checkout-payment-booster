define(
    [
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/error-processor',
        'Bold_CheckoutPaymentBooster/js/model/bold-frontend-client',
        'Bold_CheckoutPaymentBooster/js/model/fastlane',
        'Bold_CheckoutPaymentBooster/js/action/convert-address',
        'Magento_Checkout/js/model/quote',
        'checkoutData',
        'Magento_Checkout/js/model/full-screen-loader',
        'uiRegistry',
        'underscore',
        'ko'
    ], function (
        MagentoPayment,
        errorProcessor,
        boldFrontendClient,
        fastlane,
        convertAddressAction,
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
                /** @type {{getPaymentToken: function}} */
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
                this.waitForPaymentContainer();
            },
            /**
             * Wait for the payment container to be rendered before rendering the Fastlane component.
             *
             * This is necessary because the payment container is rendered asynchronously.
             *
             * @returns {void}
             */
            waitForPaymentContainer: function () {
                const observer = new MutationObserver(function () {
                    if (quote.shippingAddress().firstname && document.querySelector(this.paymentContainer)) {
                        observer.disconnect();
                        this.renderCardComponent();
                        if (window.checkoutConfig.bold_fastlane.memberAuthenticated === true) {
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
                const options = {
                    fields: {
                        phoneNumber: {
                            prefill: quote.shippingAddress().telephone
                        }
                    },
                    styles: {}
                };
                const shippingAddress = {
                    firstName: quote.shippingAddress().firstname,
                    lastName: quote.shippingAddress().lastname,
                    streetAddress: quote.shippingAddress().street[0],
                    extendedAddress: quote.shippingAddress().street[1],
                    locality: quote.shippingAddress().city,
                    region: quote.shippingAddress().regionCode,
                    postalCode: quote.shippingAddress().postcode,
                    countryCodeAlpha2: quote.shippingAddress().countryId,
                    phoneNumber: quote.shippingAddress().telephone
                };
                try {
                    const fastlaneInstance = await fastlane.getFastlaneInstance();
                    this.fastlanePaymentComponent = await fastlaneInstance.FastlanePaymentComponent(
                        {
                            options,
                            shippingAddress
                        }
                    );
                    this.fastlanePaymentComponent.render(this.paymentContainer);
                } catch (e) {
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
                const placeMagentoOrder = this._super.bind(this);
                this.fastlanePaymentComponent.getPaymentToken().then((tokenResponse) => {
                    this.processBoldOrder(tokenResponse).then(() => {
                        const orderPlacementResult = placeMagentoOrder(data, event);
                        loader.stopLoader()
                        return orderPlacementResult;
                    }).catch((error) => {
                        const errorMessage = error.responseJSON && error.responseJSON.errors
                            ? error.responseJSON.errors[0].message
                            : error;
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
                    const paymentResult = await boldFrontendClient.post(
                        'payments',
                        {
                            'gateway_public_id': fastlane.getGatewayPublicId(),
                            'currency': quote.totals().quote_currency_code,
                            'amount': quote.totals().grand_total * 100,
                            'token': tokenResponse.id
                        }
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
                const billingAddress = tokenResponse.paymentSource
                && tokenResponse.paymentSource.card
                && tokenResponse.paymentSource.card.billingAddress
                    ? tokenResponse.paymentSource.card.billingAddress : null;
                if (!billingAddress) {
                    return;
                }
                if (!billingAddress.firstName) {
                    billingAddress.firstName = quote.shippingAddress().firstname;
                }
                if (!billingAddress.lastName) {
                    billingAddress.lastName = quote.shippingAddress().lastname;
                }
                if (!billingAddress.phoneNumber) {
                    billingAddress.phoneNumber = quote.shippingAddress().telephone;
                }
                const billingAddressData = convertAddressAction(billingAddress, 'braintree');
                quote.billingAddress(billingAddressData);
            }
        });
    });
