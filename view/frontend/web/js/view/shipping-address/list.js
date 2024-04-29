define(
    [
        'ko',
        'Magento_Checkout/js/view/shipping-address/list',
        'Magento_Customer/js/model/address-list',
        'Bold_CheckoutPaymentBooster/js/action/show-shipping-address-form',
        'Bold_CheckoutPaymentBooster/js/model/fastlane',
    ],
    function (
        ko,
        shippingAddressList,
        customerAddressList,
        showShippingAddressFormAction,
        fastlane
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
                if (!fastlane.isEnabled()) {
                    this.visible(false);
                    return;
                }
                this._super();
                customerAddressList.subscribe(function (changes) {
                        let self = this;
                        changes.forEach(function (change) {
                            if (change.status === 'deleted' && customerAddressList().length === 0) {
                                self.visible(false);
                                showShippingAddressFormAction();
                            }
                        });
                    },
                    this,
                    'arrayChange'
                );
            },

            /** @inheritdoc */
            createRendererComponent: function (address, index) {
                this._super(address, index);
                this.visible(true);
                fastlane.getFastlaneInstance().then((fastlaneInstance) => {
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
