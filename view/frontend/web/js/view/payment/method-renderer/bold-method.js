define([
    'Magento_Checkout/js/view/payment/default',
    'Bold_CheckoutPaymentBooster/js/model/bold-frontend-client',
    'Bold_CheckoutPaymentBooster/js/model/fastlane',
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
    'Magento_Checkout/js/view/form/element/email',
    'uiRegistry',
    'underscore',
    'ko',
    'mage/translate',
], function (
    Component,
    boldClient,
    fastlane,
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
    emailElement,
    registry,
    _,
    ko,
    $t
) {
    'use strict';
    return Component.extend({
        defaults: {
            template: 'Bold_CheckoutPaymentBooster/payment/bold',
            paymentType: null,
            iframeWindow: null,
            customerSynced: ko.observable(window.isCustomerLoggedIn),
            billingAddressSynced: ko.observable(quote.billingAddress() !== null),
            pigiInitialized: ko.observable(false),
            isVisible: ko.observable(true),
            iframeSrc: ko.observable(null),
            isPigiLoading: ko.observable(true),
            error: $t('An error occurred while processing your payment. Please try again.'),
        },

        /** @inheritdoc */
        initialize: function () {
            const self = this;
            this._super(); //call Magento_Checkout/js/view/payment/default::initialize()
            if (!window.checkoutConfig.bold || !window.checkoutConfig.bold.payment_booster) {
                this.isVisible(false);
                return;
            }
            if (window.checkoutConfig.bold.scaRedirect) {
                this.placeOrder({}, null);
                return;
            }
            this.subscribeToPIGI();
            this.customerSynced.subscribe(function (synced) {
                if (synced && this.billingAddressSynced() && this.pigiInitialized()) {
                    this.isPigiLoading(false);
                }
            }.bind(this));
            this.billingAddressSynced.subscribe(function (synced) {
                if (synced && this.customerSynced() && this.pigiInitialized()) {
                    this.isPigiLoading(false);
                }
            }.bind(this));
            this.pigiInitialized.subscribe(function (initialized) {
                if (initialized && this.customerSynced() && this.billingAddressSynced()) {
                    this.isPigiLoading(false);
                }
            }.bind(this));
            this.awaitingRefreshBeforePlacingOrder = false;
            this.messageContainer.errorMessages.subscribe(function (errorMessages) {
                if (errorMessages.length > 0) {
                    loader.stopLoader();
                }
            });
            const sendBillingAddressData = _.debounce(
                function () {
                    this.sendBillingAddress();
                }.bind(this),
                500
            );
            quote.billingAddress.subscribe(function () {
                sendBillingAddressData();
            }, this);
            const email = registry.get('index = customer-email');
            if (email) {
                const sendGuestCustomerInfoData = _.debounce(
                    function () {
                        this.sendGuestCustomerInfo();
                    }.bind(this),
                    500
                );
                email.email.subscribe(function () {
                    if (email.validateEmail()) {
                        sendGuestCustomerInfoData();
                    }
                }.bind(this));
            }
            this.sendBillingAddress();
            this.initializePaymentGateway();
            registry.async('checkoutProvider')(
                function (checkoutProvider) {
                    checkoutProvider.on('shippingAddress', self.onAddressChanged.bind(self));
                    checkoutProvider.on('billingAddress', self.onAddressChanged.bind(self));
                });
            registry.async('checkout.customer-information.email')(
                function (emailComponent) {
                    emailComponent.emailFocused.subscribe(self.onEmailChanged.bind(self));
                });
        },

        /**
         * Observe email change event and synchronize address.
         *
         * @param focused
         */
        onEmailChanged: function (focused) {
            if (!focused && emailElement().validateEmail()) {
                this.sendBillingAddress();
            }
        },

        /**
         * Observe address change event and synchronize it.
         *
         * @param addressData
         * @param changes
         */
        onAddressChanged: function (addressData, changes) {
            if (changes && changes.length !== 0) {
                this.sendBillingAddress();
            }
        },

        /**
         * Initialize PIGI iframe.
         */
        initializePaymentGateway: function () {
            console.log('initializing pigi...');
            this.iframeSrc(window.checkoutConfig.bold.payment_booster.payment.iframeSrc);
        },

        /** @inheritdoc */
        selectPaymentMethod: function () {
            this._super();
            if (this.iframeWindow) {
                this.iframeWindow.postMessage({actionType: 'PIGI_REFRESH_ORDER'}, '*');
            }
            return true;
        },

        /**
         * Refresh the order to get the recent cart updates.
         *
         * @returns {void}
         */
        refreshAndAddPayment: function () {
            if (!this.iframeWindow) {
                return;
            }
            const refreshAction = {actionType: 'PIGI_REFRESH_ORDER'};
            this.awaitingRefreshBeforePlacingOrder = true;
            this.iframeWindow.postMessage(refreshAction, '*');
        },

        /** @inheritdoc */
        placeOrder: function (data, event) {
            loader.startLoader();
            if(!window.checkoutConfig.bold.scaRedirect){
                if (!this.iframeWindow) {
                    return false;
                }

                const clearAction = {actionType: 'PIGI_CLEAR_ERROR_MESSAGES'};
                this.iframeWindow.postMessage(clearAction, '*');

                if (!this.paymentType) {
                    this.refreshAndAddPayment();
                    return false;
                }
            }
            const defaultPlaceOrder = this._super;
            this.updateBoldOrder().then(() => {
                const orderPlacementResult = defaultPlaceOrder.call(this, data, event);//call Magento_Checkout/js/view/payment/default::placeOrder()
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
        updateBoldOrder: async function () {
            try {
                await boldClient.get('refresh');
                const taxesResult = await boldClient.post('taxes');
                this.updateCart(taxesResult.data);
            } catch (e) {
                console.error('Error processing order', e);
                throw new Error(this.error);
            }
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
            window.addEventListener('message', ({ origin, data }) => {
                if (origin !== window.checkoutConfig.bold.origin) {
                    return;
                }
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
                            if (fastlane.isEnabled()) {
                                this.iframeWindow.postMessage({ actionType: 'PIGI_HIDE_CREDIT_CARD_OPTION' }, '*');
                            }
                            break;
                        case 'PIGI_INITIALIZED':
                            this.iframeWindow = iframeElement.contentWindow;
                            if (data.payload && data.payload.height && iframeElement) {
                                iframeElement.height = Math.round(data.payload.height) + 'px';
                            }
                            this.pigiInitialized(true);
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
                            break;
                        case 'PIGI_HANDLE_SCA': 
                            this.handleSca(iframeElement, data.payload);
                            break;
                        case 'PIGI_DISPLAY_IN_FULL_PAGE':
                            this.displayScaFullScreen(iframeElement);
                            break;
                        case 'PIGI_DISPLAY_IN_FULL_PAGE_DONE': 
                            this.hideScaFullScreen(iframeElement)
                            break;
                    }
                }
            });
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
        },
        /**
         * Send guest customer info to Bold.
         *
         * @private
         * @returns {Promise<void>}
         */
        sendGuestCustomerInfo: async function () {
            if (window.isCustomerLoggedIn) {
                return;
            }
            try {
                const result = await boldClient.post('customer/guest')
                this.customerSynced(!result.errors);
                this.messageContainer.errorMessages([]);
            } catch (error) {
                this.displayErrorMessage(error)
            }
        },
        /**
         * Synchronize billing address with Bold.
         *
         * @private
         * @returns {Promise<void>}
         */
        sendBillingAddress: async function () {
            await this.sendGuestCustomerInfo();
            try {
                const result = await boldClient.post('addresses/billing');
                this.billingAddressSynced(!result.errors);
                this.messageContainer.errorMessages([]);
            } catch (error) {
                this.displayErrorMessage(error);
            }
        },

        handleSca: async function (iframeElement, payload) {
            switch (payload.step) {
                case 'REDIRECT':
                    const redirectUrl = window.location.href
                    const scaUrl = payload.data.url;
                    window.checkoutConfig.bold.scaRedirect = true;
                    window.location.href = scaUrl + '&redirect_uri=' + encodeURIComponent(redirectUrl);
                    break;
                case 'DISPLAYED': 
                    this.displayScaFullScreen(iframeElement);
                    break;
                case 'COMPLETED':
                    loader.startLoader();
                    break;
                case 'FAILED':
                    this.hideScaFullScreen(iframeElement);
                    break;
            }
        },

        displayScaFullScreen: function(iframeElement){
            loader.stopLoader(true);
            iframeElement.classList.add('payment__iframe--display-sca');
        },

        hideScaFullScreen: function(iframeElement){
            iframeElement.classList.remove('payment__iframe--display-sca');
        },
    });
});
