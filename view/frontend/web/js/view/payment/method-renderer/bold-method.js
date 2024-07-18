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
    'Bold_CheckoutPaymentBooster/js/action/reload-cart-action'
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
    reloadCartAction
) {
    'use strict';
    return DefaultPaymentComponent.extend({
        defaults: {
            template: 'Bold_CheckoutPaymentBooster/payment/bold',
            paymentType: null,
            iframeWindow: null,
            orderHydrated: ko.observable(false),
            pigiInitialized: ko.observable(false),
            isVisible: ko.observable(true),
            iframeSrc: ko.observable(null),
            isPigiLoading: ko.observable(true),
            error: $t('An error occurred while processing your payment. Please try again.'),
        },

        /** @inheritdoc */
        initialize: function () {
            this._super(); //call Magento_Checkout/js/view/payment/default::initialize()
            if (!window.checkoutConfig.bold || !window.checkoutConfig.bold.paymentBooster) {
                this.isVisible(false);
                return;
            }
            this.subscribeToPIGI();
            this.pigiInitialized.subscribe(function (initialized) {
                if (initialized) {
                    this.isPigiLoading(false);
                }
            }.bind(this));
            this.messageContainer.errorMessages.subscribe(function (errorMessages) {
                if (errorMessages.length > 0) {
                    fullscreenLoader.stopLoader();
                }
            });
            const delayedHydrateOrder = _.debounce(
                async function () {
                    await hydrateOrderAction(this.displayErrorMessage.bind(this));
                    if (window.checkoutConfig.bold.hydratedOrderAddress) {
                        this.initializePaymentGateway();
                    }
                }.bind(this),
                500
            );
            hydrateOrderAction(this.displayErrorMessage.bind(this)).then(() => {
                if (window.checkoutConfig.bold.hydratedOrderAddress) {
                    this.initializePaymentGateway();
                }
            }).finally(() => {
                quote.billingAddress.subscribe(function () {
                    delayedHydrateOrder();
                }, this);
            });
        },
        /**
         * Initialize PIGI iframe.
         */
        initializePaymentGateway: function () {
            console.log('initializing pigi...');
            this.iframeSrc(window.checkoutConfig.bold.paymentBooster.payment.iframeSrc);
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
            if (!this.iframeWindow) {
                return false;
            }
            const clearAction = {actionType: 'PIGI_CLEAR_ERROR_MESSAGES'};
            this.iframeWindow.postMessage(clearAction, '*');
            if (!this.paymentType) {
                this.iframeWindow.postMessage({actionType: 'PIGI_ADD_PAYMENT'}, '*');
                return false;
            }
            return this._super(data, event);
        },

        /**
         * Refresh the order to get the recent cart updates, calculate taxes and authorize|capture payment on Bold side.
         *
         * @return {Promise<void>}
         */
        processBoldOrder: async function () {
            // todo: implement the logic to authorize|capture payment on Bold side
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
         * Subscribe to PIGI events.
         *
         * @private
         * @returns {void}
         */
        subscribeToPIGI() {
            window.addEventListener('message', ({data}) => {
                const responseType = data.responseType;
                const iframeElement = document.getElementById('PIGI');
                if (responseType) {
                    switch (responseType) {
                        case 'PIGI_UPDATE_HEIGHT':
                            if (iframeElement.height === Math.round(data.payload.height) + 'px') {
                                return;
                            }
                            iframeElement.height = Math.round(data.payload.height) + 'px';
                            break;
                        case 'PIGI_INITIALIZED':
                            this.iframeWindow = iframeElement.contentWindow;
                            /* if (fastlane.isEnabled()) {
                                 this.iframeWindow.postMessage({actionType: 'PIGI_HIDE_CREDIT_CARD_OPTION'}, '*');
                             }*/ //todo: uncomment after initial state with additional payment methods will be available
                            if (data.payload && data.payload.height && iframeElement) {
                                iframeElement.height = Math.round(data.payload.height) + 'px';
                            }
                            this.pigiInitialized(true);
                            break;
                        case 'PIGI_CHANGED_ORDER':
                            reloadCartAction();
                            break;
                        case 'PIGI_ADD_PAYMENT':
                            this.messageContainer.errorMessages([]);
                            fullscreenLoader.stopLoader(true);
                            if (!data.payload.success) {
                                this.paymentType = null;
                                return;
                            }
                            this.paymentType = data.payload.paymentType;
                            this.placeOrder({}, null);
                    }
                }
            });
        },

        /**
         * Synchronize quote data with Bold.
         *
         * @private
         * @returns {Promise<void>}
         */
        hydrateOrder: async function () {
            try {
                const urlTemplate = window.isCustomerLoggedIn
                    ? 'rest/V1/shops/{{shopId}}/cart/hydrate/:publicOrderId'
                    : 'rest/V1/shops/{{shopId}}/guest-cart/:cartId/hydrate/:publicOrderId';
                const url = urlTemplate.replace(':cartId', window.checkoutConfig.quoteData.entity_id)
                    .replace(':publicOrderId', window.checkoutConfig.bold.publicOrderId);
                await platformClient.put(url, {});
                this.messageContainer.errorMessages([]);
            } catch (error) {
                this.displayErrorMessage(error)
            }
        },
    });
});
