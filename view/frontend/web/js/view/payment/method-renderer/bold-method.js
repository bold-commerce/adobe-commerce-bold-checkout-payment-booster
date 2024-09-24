define([
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Customer/js/customer-data',
    'checkoutData',
    'underscore',
    'ko',
    'mage/translate',
    'Bold_CheckoutPaymentBooster/js/model/platform-client',
    'Bold_CheckoutPaymentBooster/js/model/fastlane',
    'Bold_CheckoutPaymentBooster/js/action/hydrate-order-action',
    'Bold_CheckoutPaymentBooster/js/action/reload-cart-action',
    'Bold_CheckoutPaymentBooster/js/model/address',
    'Bold_CheckoutPaymentBooster/js/model/bold-frontend-client'
], function (
    DefaultPaymentComponent,
    quote,
    fullscreenLoader,
    customerData,
    checkoutData,
    _,
    ko,
    $t,
    platformClient,
    fastlane,
    hydrateOrderAction,
    reloadCartAction,
    addressModel,
    boldFrontendClient
) {
    'use strict';
    return DefaultPaymentComponent.extend({
        defaults: {
            template: 'Bold_CheckoutPaymentBooster/payment/bold',
            paymentType: null,
            iframeWindow: null,
            boldPayments: null,
            spiInitialized: false,
            paymentId: ko.observable(null),
            isVisible: ko.observable(true),
            isSpiLoading: ko.observable(true),
            error: $t('An error occurred while processing your payment. Please try again.'),
        },

        /** @inheritdoc */
        initialize: function () {
            this._super(); //call Magento_Checkout/js/view/payment/default::initialize()
            this.isVisible(window.checkoutConfig.bold && window.checkoutConfig.bold.paymentBooster && !fastlane.isEnabled());
            if (!window.checkoutConfig.bold || !window.checkoutConfig.bold.paymentBooster) {
                return;
            }
            this.subscribeToSpiEvents();
            this.removeFullScreenLoaderOnError();
            const delayedHydrateOrder = _.debounce(
                async function () {
                    await hydrateOrderAction(this.displayErrorMessage.bind(this));
                    if (window.checkoutConfig.bold.hydratedOrderAddress) {
                        this.initPaymentForm();
                    }
                }.bind(this),
                500
            );
            hydrateOrderAction(this.displayErrorMessage.bind(this)).then(() => {
                if (window.checkoutConfig.bold.hydratedOrderAddress) {
                    this.initPaymentForm();
                }
            }).finally(() => {
                quote.billingAddress.subscribe(function () {
                    delayedHydrateOrder();
                }, this);
            });
        },

        /**
         * Load SPI SDK.
         *
         * @returns {Promise<void>}
         */
        initPaymentForm: async function () {
            if (this.spiInitialized) {
                return;
            }
            await this.loadScript(window.checkoutConfig.bold.epsStaticUrl + '/js/payments_sdk.js');
            const initialData = {
                'eps_url': window.checkoutConfig.bold.epsUrl,
                'eps_bucket_url': window.checkoutConfig.bold.epsStaticUrl,
                'group_label': window.checkoutConfig.bold.configurationGroupLabel,
                'trace_id': window.checkoutConfig.bold.publicOrderId,
                'payment_gateways': [
                    {
                        'gateway_id': Number(window.checkoutConfig.bold.gatewayId),
                        'auth_token': window.checkoutConfig.bold.epsAuthToken,
                        // TODO
                        'currency': 'USD',
                    }
                ],
                'callbacks': {
                    'onCreatePaymentOrder': async function (paymentType, paymentPayload) {
                        if (paymentType !== 'ppcp') {
                            return;
                        }
                        const walletPayResult = await boldFrontendClient.post(
                            'wallet_pay/create_order',
                            paymentPayload
                        );
                        if (walletPayResult.errors) {
                            return Promise.reject('An error occurred while processing your payment. Please try again.');
                        }
                        if (walletPayResult.data) {
                            return walletPayResult.data
                        } else {
                            throw 'Unable to create order';
                        }
                    }
                }
            };
            const boldPayments = new window.bold.Payments(initialData);
            boldPayments.renderPayments('SPI');
            this.spiInitialized = true;
        },

        /**
         * Load specified script with attributes.
         *
         * @returns {Promise<void>}
         */
        loadScript: async function (src, attributes = {}) {
            return new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = src;
                script.async = true;
                script.onload = resolve;
                script.onerror = reject;
                if (attributes.constructor === Object) {
                    Object.keys(attributes).forEach((key) => {
                        script.setAttribute(key, attributes[key]);
                    });
                }
                document.head.appendChild(script);
            });
        },

        /** @inheritdoc */
        selectPaymentMethod: function () {
            this._super();
            if (this.iframeWindow) {
                this.iframeWindow.postMessage({actionType: 'PIGI_REFRESH_ORDER'}, '*');
            }
            return true;
        },

        /** @inheritdoc */
        placeOrder: function (data, event) {
            fullscreenLoader.startLoader();
            const callback = this._super.bind(this);
            this.tokenize()
            this.paymentId.subscribe((id) => {
                if (id != null) {
                    callback(data, event);
                }
            });
        },

        /**
         * Display error message.
         *
         * @private
         * @param {{}} error
         */
        displayErrorMessage: function (error) {
            let message,
                subType
            try {
                message = error.responseJSON.errors[0].message
                subType = error.responseJSON.errors[0].sub_type
            } catch (exception) {
                message = this.error
                subType = ''
            }
            if (!this.iframeWindow) {
                this.messageContainer.errorMessages([message]);
                return;
            }
            const action = {
                actionType: 'PIGI_DISPLAY_ERROR_MESSAGE',
                payload: {
                    error: {
                        message: message,
                        sub_type: subType,
                    }
                }
            };
            try {
                this.iframeWindow.postMessage(action, '*');
            } catch (e) {
                this.messageContainer.errorMessages([this.error]);
            }
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
            const iframeWindow = document.getElementById('spi_frame_SPI').contentWindow;
            const address = addressModel.getAddress();
            const payload = {
                billing_address: {
                    first_name: address.firstname,
                    last_name: address.lastname,
                    address_line_1: address.street[0],
                    address_line_2: address.street[1],
                    province_code: address.region,
                    city: address.city,
                    postal_code: address.postcode,
                    country_code: address.country_id,
                }
            };
            iframeWindow.postMessage({actionType: 'ACTION_SPI_TOKENIZE', payload: payload}, '*');
        },
        /**
         * Subscribe to SPI iframe events.
         *
         * @returns {void}
         */
        subscribeToSpiEvents() {
            window.addEventListener('message', ({origin, data}) => {
                const eventType = data?.eventType;
                switch (eventType) {
                    case 'EVENT_SPI_INITIALIZED':
                        this.isSpiLoading(false);
                        break;
                    case 'EVENT_SPI_TOKENIZED':
                        if (!data.payload.success) {
                            this.paymentId(null);
                            console.log('Tokenized');
                            this.isSpiLoading(false);
                            return;
                        }
                        this.paymentId(data.payload?.payload?.data?.payment_id);
                        break;
                    case 'EVENT_SPI_TOKENIZE_FAILED':
                        this.paymentId(null);
                        console.log('Failed to tokenize');
                        this.isSpiLoading(false);
                        break;
                    case 'EVENT_SPI_PAYMENT_ORDER_CREATE':
                        console.log('Payment order created', data);
                        break;
                }
            });
        },
    });
});
