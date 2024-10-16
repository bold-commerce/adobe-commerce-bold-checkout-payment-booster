define([
    'uiComponent',
    'ko',
    'jquery',
    'Bold_CheckoutPaymentBooster/js/model/spi',
    'Magento_Checkout/js/model/quote',
    'checkoutData',
    'uiRegistry',
    'Bold_CheckoutPaymentBooster/js/action/get-express-pay-order-action',
    'Magento_Checkout/js/action/place-order',
    'Magento_Checkout/js/action/redirect-on-success'
], function (
    Component,
    ko,
    $,
    spi,
    quote,
    checkoutData,
    registry,
    getExpressPayOrderAction,
    placeOrderAction,
    redirectOnSuccessAction
) {
    'use strict';

    /**
     * PayPal Express pay button component.
     */
    return Component.extend({
        defaults: {
            template: 'Bold_CheckoutPaymentBooster/express-pay',
            paymentId: ko.observable(null),
            paymentApprovalData: ko.observable(null)
        },
        isVisible: ko.observable(false),
        /** @inheritdoc */

        initialize: async function () {
            this._super();

            // this._setVisibility();
            this.subscribeToSpiEvents();
            this._setVisibility();
            window.addEventListener('hashchange', this._setVisibility.bind(this));
        },
        /**
         * Set the visibility of the component.
         * @private
         */
        _setVisibility: function () {
            console.log('SET VISIBILITY');
            this.isVisible(window.location.hash === '#shipping');
            const onShippingStep = window.location.hash === '#shipping';
            // this.isVisible(window.location.hash === '#shipping' && expressPay.isEnabled());

            // On step change remove any other instance, can only have one on a page
            const ppcpExpressContainer = document.getElementById('ppcp-express-payment');
            if (ppcpExpressContainer) {
                ppcpExpressContainer.remove();
            }

            if (onShippingStep) {
                this._renderExpressPayments();
            }

            this.isVisible(onShippingStep);
        },

        _renderExpressPayments: function() {
            const containerId = 'express-pay-buttons';
            const observer = new MutationObserver(async () => {
                let boldPaymentsInstance;

                if (document.getElementById(containerId)) {
                    observer.disconnect();

                    try {
                        boldPaymentsInstance = await spi.getPaymentsClient();
                    } catch (error) {
                        console.error('Could not instantiate Bold Payments Client.', error);

                        return;
                    }

                    const allowedCountries = window.checkoutConfig.bold?.countries ?? [];
                    const walletOptions = {
                        shopName: window.checkoutConfig.bold?.shopName ?? '',
                        isPhoneRequired: window.checkoutConfig.bold?.isPhoneRequired ?? true,
                        fastlane: window.checkoutConfig.bold?.fastlane,
                        allowedCountryCodes: allowedCountries
                    };

                    boldPaymentsInstance.renderWalletPayments(containerId, walletOptions);
                }
            });
            observer.observe(document.documentElement, {
                childList: true,
                subtree: true
            });
        },

        placeOrder: async function () {
            const paymentApprovalData = this.paymentApprovalData();
            const paymentMethodData = {
                method: 'bold',
                additional_data: {
                    order_id: paymentApprovalData?.payment_data.order_id
                }
            };
            const messageContainer = registry.get('checkout.errors').messageContainer;
            let order;

            if (paymentApprovalData === null) {
                console.error('Express Pay payment data is not set.');

                return;
            }

            try {
                order = await getExpressPayOrderAction(
                    paymentApprovalData.gateway_id,
                    paymentApprovalData.payment_data.order_id
                );
            } catch (error) {
                console.error('Could not retrieve Express Pay order.', error);

                return;
            }

            quote.guestEmail = order.email;

            spi.updateAddress('shipping', this._convertAddress(order.shipping_address, order));
            spi.updateAddress('billing', this._convertAddress(order.billing_address, order));

            try {
                await spi.saveShippingInformation(true);
            } catch (error) {
                console.error('Could not save shipping information for Express Pay order.', error);

                return;
            }

            $.when(placeOrderAction(paymentMethodData, messageContainer))
                .done(
                    function () {
                        redirectOnSuccessAction.execute();
                    }
                );
        },

        // NOTE: The tokenize and subscribeToSpiEvents are copied over directly from bold-spi.js
        // TODO: See if there is a better way of handling this

        /**
         * Send tokenize action to SPI iframe.
         *
         * @return void
         */
        tokenize: function () {
            const iframeWindow = document.getElementById('spi_frame_SPI').contentWindow;
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
                console.log("SPI EVENT", eventType);
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

        /**
         * @param {Object} order
         * @param {Object} address
         * @returns {Object}
         * @private
         */
        _convertAddress: function (address, order) {
            address.first_name = order.first_name;
            address.last_name = order.last_name;
            address.state = address.province;
            address.country_code = address.country;
            address.email = order.email;

            delete address.province;
            delete address.country;

            return address;
        },
    });
});
