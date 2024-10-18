define(
    [
        'ko',
        'Magento_Checkout/js/view/shipping-address/list',
        'Magento_Customer/js/model/address-list',
        'Bold_CheckoutPaymentBooster/js/action/show-shipping-address-form',
        'Bold_CheckoutPaymentBooster/js/model/spi',
        'Bold_CheckoutPaymentBooster/js/model/fastlane',
        'Magento_Checkout/js/model/quote'
    ],
    function (
        ko,
        shippingAddressList,
        customerAddressList,
        showShippingAddressFormAction,
        spi,
        fastlane,
        quote
    ) {
        'use strict';

        /**
         * Fastlane shipping address list component.
         *
         * @type {Object}
         */
        return shippingAddressList.extend({
            defaults: {
                template: 'Bold_CheckoutPaymentBooster/shipping-address/list',
                rendererTemplates: {
                    'fastlane-shipping-address': {
                        component: 'Bold_CheckoutPaymentBooster/js/view/shipping-address/address-renderer/default'
                    }
                },
                visible: ko.observable(false),
            },

            /** @inheritdoc */
            initialize: function () {
                this._super();
                if (!fastlane.isAvailable()) {
                    this.visible(false);
                    return;
                }
                quote.shippingAddress.subscribe(function (address) {
                    if (address && address.getType() === 'fastlane-shipping-address') {
                        this.createRendererComponent(address, 0);
                        return;
                    }
                    this.visible(false);
                    showShippingAddressFormAction();
                }.bind(this));
            },

            /** @inheritdoc */
            createRendererComponent: function (address, index) {
                if (address.getType() !== 'fastlane-shipping-address') {
                    return;
                }
                this._super(address, index);
                if (!fastlane.isAvailable()) {
                    this.visible(false);
                    return;
                }
                this.visible(true);
                spi.getFastlaneInstance().then((fastlaneInstance) => {
                    if (!fastlaneInstance) {
                        return;
                    }
                    fastlaneInstance.FastlaneWatermarkComponent({
                        includeAdditionalInfo: true
                    }).then((WatermarkComponent) => {
                        WatermarkComponent.render('#fastlane-address-list-watermark-container');
                    });
                }).catch((error) => {
                    console.log(error);
                });
            }
        });
    });
