define([
    'Magento_Checkout/js/view/payment/default',
    'Bold_CheckoutPaymentBooster/js/model/bold-frontend-client',
    'Magento_Checkout/js/model/quote',
    'checkoutData',
    'Bold_CheckoutPaymentBooster/js/action/convert-bold-address',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/action/get-totals',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/address-converter',
    'Magento_Checkout/js/action/select-shipping-address',
    'Magento_Checkout/js/action/select-billing-address',
    'Magento_Checkout/js/action/select-shipping-method',
    'uiRegistry',
    'underscore',
    'ko'
], function (
    Component,
    boldClient,
    quote,
    checkoutData,
    convertBoldAddressAction,
    loader,
    getTotals,
    customerData,
    coreAddressConverter,
    selectShippingAddressAction,
    selectBillingAddressAction,
    selectShippingMethodAction,
    registry,
    _,
    ko
) {
    'use strict';
    return Component.extend({
        defaults: {
            template: 'Bold_CheckoutPaymentBooster/payment/bold',
            paymentType: null,
            isVisible: ko.observable(true),
            iframeSrc: ko.observable(null),
            isPigiLoading: ko.observable(true),
        },

        /**
         * @inheritDoc
         */
        initialize: function () {
            this._super(); //call Magento_Checkout/js/view/payment/default::initialize()
            if (window.checkoutConfig.bold === undefined) {
                this.isVisible(false);
                return;
            }
            this.subscribeToPIGI();
            this.customerIsGuest = !!Number(window.checkoutConfig.bold.customerIsGuest);
            this.awaitingRefreshBeforePlacingOrder = false;
            this.messageContainer.errorMessages.subscribe(function (errorMessages) {
                if (errorMessages.length > 0) {
                    loader.stopLoader();
                }
            });

            const sendRefreshOrder = _.debounce(
                function () {
                    this.refreshOrder();
                }.bind(this),
                500
            );

            sendRefreshOrder();

            quote.billingAddress.subscribe(function () {
                sendRefreshOrder();
            }, this);
            const email = registry.get('index = customer-email');
            if (email) {
                email.email.subscribe(function () {
                    if (email.validateEmail()) {
                        sendRefreshOrder();
                    }
                }.bind(this));
            }
        },

        initializePaymentGateway: function () {
            console.log('initializing pigi...');
            // Set frame src once /refresh is done
            this.iframeSrc(window.checkoutConfig.bold.payment_booster.payment.iframeSrc);
        },

        /**
         * @inheritDoc
         */
        selectPaymentMethod: function () {
            this._super();
            if (this.iframeWindow) {
                this.iframeWindow.postMessage({actionType: 'PIGI_REFRESH_ORDER'}, '*');
            }
            return true;
        },

        /**
         * @inheritDoc
         */
        refreshAndAddPayment: function () {
            if (this.iframeWindow) {
                const refreshAction = {actionType: 'PIGI_REFRESH_ORDER'};
                this.awaitingRefreshBeforePlacingOrder = true;
                this.iframeWindow.postMessage(refreshAction, '*');
            }
        },

        /**
         * @inheritDoc
         */
        placeOrder: function (data, event) {
            loader.startLoader();
            if (!this.iframeWindow) {
                return false;
            }

            const clearAction = {actionType: 'PIGI_CLEAR_ERROR_MESSAGES'};
            this.iframeWindow.postMessage(clearAction, '*');

            if (!this.paymentType) {
                this.refreshAndAddPayment();
                return false;
            }
            const originalPlaceOrder = this._super;
            this.processBoldOrder().then(() => {
                const orderPlacementResult = originalPlaceOrder.call(this, data, event);//call Magento_Checkout/js/view/payment/default::placeOrder()
                if (!orderPlacementResult) {
                    loader.stopLoader()
                }
                return orderPlacementResult;
            }).catch((error) => {
                this.displayErrorMessage(error);
                loader.stopLoader();
                return false;
            });
        },
        /**
         * Refresh the order to get the recent cart updates, calculate taxes and authorize|capture payment on Bold side.
         *
         * @return {Promise<void>}
         */
        processBoldOrder: async function () {
            const refreshResult = await boldClient.get('refresh');
            const taxesResult = await boldClient.post('taxes');
            const processOrderResult = await boldClient.post('process_order');
            if (refreshResult.errors || taxesResult.errors || processOrderResult.errors) {
                throw new Error('An error occurred while processing your payment. Please try again.');
            }
            this.updateCart(processOrderResult.data);
        },
        /**
         * Display error message in PIGI iframe.
         *
         * @private
         * @param {string} message
         */
        displayErrorMessage: function (message) {
            const iframeElement = document.getElementById('PIGI');
            const iframeWindow = iframeElement.contentWindow;
            const action = {
                actionType: 'PIGI_DISPLAY_ERROR_MESSAGE',
                payload: {
                    error: {
                        message: message,
                        sub_type: 'string_to_categorize_error',
                    }
                }
            };
            try {
                iframeWindow.postMessage(action, '*');
            } catch (e) {
                console.error('Error displaying error message in PIGI iframe', e);
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
                const addPaymentAction = {actionType: 'PIGI_ADD_PAYMENT'};
                if (responseType) {
                    switch (responseType) {
                        case 'PIGI_UPDATE_HEIGHT':
                            if (iframeElement.height === Math.round(data.payload.height) + 'px') {
                                return;
                            }
                            iframeElement.height = Math.round(data.payload.height) + 'px';
                            break;
                        case 'PIGI_INITIALIZED':
                            if (data.payload && data.payload.height && iframeElement) {
                                iframeElement.height = Math.round(data.payload.height) + 'px';
                            }
                            this.iframeWindow = iframeElement ? iframeElement.contentWindow : null;
                            this.isPigiLoading(false);
                            break;
                        case 'PIGI_REFRESH_ORDER':
                            if (this.awaitingRefreshBeforePlacingOrder) {
                                this.iframeWindow.postMessage(addPaymentAction, '*');
                                this.awaitingRefreshBeforePlacingOrder = false;
                            }
                            break;
                        case 'PIGI_CHANGED_ORDER':
                            customerData.reload(['bold'], false).then((cartData) => {
                                const billingAddress = coreAddressConverter.formAddressDataToQuoteAddress(cartData.bold.billingAddress);
                                selectBillingAddressAction(billingAddress);
                                if (cartData.bold.shippingAddress) {
                                    const shippingAddress = coreAddressConverter.formAddressDataToQuoteAddress(cartData.bold.shippingAddress);
                                    selectShippingAddressAction(shippingAddress);
                                    checkoutData.setSelectedShippingAddress(shippingAddress.getKey());
                                }
                                if (cartData.bold.shippingMethod) {
                                    selectShippingMethodAction(cartData.bold.shippingMethod);
                                }
                                getTotals([]);
                            }).catch((error) => {
                                console.error('Error reloading customer data', error);
                            });
                            break;
                        case 'PIGI_ADD_PAYMENT':
                            this.messageContainer.errorMessages([]);
                            loader.stopLoader(true);
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
         * Sync Magento order with Bold.
         *
         * @private
         * @returns {void}
         */
        refreshOrder() {
            boldClient.get('refresh').then(
                function (response) {
                    this.messageContainer.errorMessages([]);
                    if (!this.isRadioButtonVisible() && !quote.shippingMethod()) {
                        return this.selectPaymentMethod(); // some one-step checkout updates shipping lines only after payment method is selected.
                    }

                    if (
                        response &&
                        response.data &&
                        response.data.application_state &&
                        response.data.application_state.customer &&
                        response.data.application_state.customer.email_address &&
                        response.data.application_state.addresses.billing &&
                        response.data.application_state.addresses.billing.address_line_1
                    ) {
                        if (this.isPigiLoading()) {
                            this.initializePaymentGateway(); // don't initialize pigi until there's a customer on the order
                        }
                        if (this.iframeWindow) {
                            this.iframeWindow.postMessage({actionType: 'PIGI_REFRESH_ORDER'}, '*');
                        }
                    }
                }.bind(this)
            );
        },
        /**
         * Update cart with the data from Bold.
         *
         * @param {{application_state: {addresses:{billing:{}, shipping:{}}}}} data
         */
        updateCart(data) {
            const billingAddress = data.application_state.addresses.billing;
            const magentoAddress = convertBoldAddressAction(billingAddress);
            selectBillingAddressAction(magentoAddress);
        }
    });
});
