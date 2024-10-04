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
    'Bold_CheckoutPaymentBooster/js/model/platform-client',
    'Bold_CheckoutPaymentBooster/js/model/fastlane',
    'Bold_CheckoutPaymentBooster/js/action/hydrate-order-action',
    'Bold_CheckoutPaymentBooster/js/action/reload-cart-action',
    'Bold_CheckoutPaymentBooster/js/action/create-wallet-pay-order-action',
    'Bold_CheckoutPaymentBooster/js/action/convert-bold-address',
    'Bold_CheckoutPaymentBooster/js/model/address'
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
    platformClient,
    fastlane,
    hydrateOrderAction,
    reloadCartAction,
    createOrderAction,
    convertBoldAddressAction,
    addressModel,
) {
    'use strict';
    return DefaultPaymentComponent.extend({
        defaults: {
            template: 'Bold_CheckoutPaymentBooster/payment/spi',
            paymentId: ko.observable(null),
            isVisible: ko.observable(false),
            isSpiLoading: ko.observable(true),
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
            this.isVisible(window.checkoutConfig.bold?.paymentBooster && !fastlane.isAvailable());
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
         * Load SPI SDK.
         *
         * @returns {Promise<void>}
         */
        initPaymentForm: async function () {
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
                        'currency': quote.totals()['base_currency_code'],
                    }
                ],
                'callbacks': {
                    'onCreatePaymentOrder': async (paymentType, paymentPayload) => {
                        if (paymentType !== 'ppcp') {
                            return;
                        }
                        const walletPayResult = await createOrderAction(paymentPayload);
                        if (walletPayResult.errors) {
                            return Promise.reject('An error occurred while processing your payment. Please try again.');
                        }
                        if (walletPayResult.data) {
                            return walletPayResult.data
                        } else {
                            throw 'Unable to create order';
                        }
                    },
                    'onUpdatePaymentOrder': async () => {
                        // Do nothing for now.
                    },
                    'onApprovePaymentOrder': async (paymentType, paymentPayload) => {
                        this.paymentId(paymentPayload.payment_id);
                        this.placeOrder({}, jQuery.Event());
                    },
                }
            };
            const boldPayments = new window.bold.Payments(initialData);
            boldPayments.renderPayments('SPI');
        },

        /** @inheritdoc */
        placeOrder: function (data, event) {
            fullscreenLoader.startLoader();
            const callback = this._super.bind(this);
            if (this.paymentId()) {
                callback(data, event);
                return;
            }
            this.tokenize()
            this.paymentId.subscribe((id) => {
                if (id != null) {
                    callback(data, event);
                }
            });
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
            window.addEventListener('message', ({data}) => {
                const eventType = data?.eventType;
                switch (eventType) {
                    case 'EVENT_SPI_INITIALIZED':
                        this.isSpiLoading(false);
                        break;
                    case 'EVENT_SPI_TOKENIZED':
                        this.paymentId(data.payload?.payload?.data?.payment_id);
                        break;
                    case 'EVENT_SPI_TOKENIZE_FAILED':
                        this.paymentId(null);
                        console.log('Failed to tokenize');
                        fullscreenLoader.stopLoader();
                        this.isSpiLoading(false);
                        break;
                }
            });
        },
    });
});
