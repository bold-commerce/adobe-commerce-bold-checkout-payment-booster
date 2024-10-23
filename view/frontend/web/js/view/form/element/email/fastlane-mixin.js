define(
    [
        'Bold_CheckoutPaymentBooster/js/model/spi',
        'Bold_CheckoutPaymentBooster/js/model/fastlane',
        'Bold_CheckoutPaymentBooster/js/view/shipping-address/list',
        'Magento_Customer/js/model/address-list',
        'Magento_Checkout/js/model/new-customer-address',
        'Magento_Checkout/js/action/create-shipping-address',
        'uiRegistry',
        'Magento_Checkout/js/model/full-screen-loader',
        'checkoutData',
        'Bold_CheckoutPaymentBooster/js/action/fastlane/set-quote-shipping-address-action',
        'Bold_CheckoutPaymentBooster/js/action/fastlane/reset-shipping-address-action',
        'Magento_Checkout/js/model/quote'
    ], function (
        spi,
        fastlane,
        addressList,
        customerAddressList,
        Address,
        createShippingAddress,
        registry,
        fullScreenLoader,
        checkoutData,
        setQuoteShippingAddressAction,
        resetShippingAddressAction,
        quote
    ) {
        'use strict';

        /**
         * Email mixin for Fastlane.
         *
         * @param {Object} emailComponent - Magento_Checkout/js/view/form/element/email.
         */
        return function (MagentoEmailComponent) {
            /**
             * Adapt the email component to work with Fastlane.
             */
            return MagentoEmailComponent.extend(
                {
                    /** @inheritdoc */
                    initConfig: function () {
                        this._super();
                        if (!fastlane.isAvailable()) {
                            return;
                        }
                        this.template = 'Bold_CheckoutPaymentBooster/form/element/email';
                        spi.getFastlaneInstance().then((fastlaneInstance) => {
                            if (!fastlaneInstance) {
                                return;
                            }
                            fastlaneInstance.FastlaneWatermarkComponent({
                                includeAdditionalInfo: true
                            }).then((WatermarkComponent) => {
                                WatermarkComponent.render('#fastlane-email-watermark-container');
                            });
                        }).catch((error) => {
                            console.log(error);
                        });
                    },
                    /**
                     * Lookup the email address in Fastlane.
                     *
                     * @return {void}
                     */
                    checkEmailAvailability: function () {
                        this._super();
                        if (!fastlane.isAvailable() || !this.email()) {
                            return;
                        }
                        this.lookupEmail().then(() => {
                            fullScreenLoader.stopLoader();
                            this.isPasswordVisible(false);
                        }).catch((error) => {
                            fullScreenLoader.stopLoader();
                            console.log(error);
                        });
                    },
                    /**
                     * Lookup the email address in Fastlane.
                     *
                     * For testing purposes use test@example.com as email and 11111 for the code.
                     *
                     * @return {Promise<void>}
                     */
                    lookupEmail: async function () {
                        try {
                            fullScreenLoader.startLoader();
                            const fastlaneInstance = await spi.getFastlaneInstance();
                            if (!fastlaneInstance) {
                                return;
                            }
                            const {identity} = fastlaneInstance;
                            const {customerContextId} = await identity.lookupCustomerByEmail(this.email());
                            fullScreenLoader.stopLoader();
                            if (customerContextId) {
                                const {
                                    authenticationState,
                                    profileData
                                } = await identity.triggerAuthenticationFlow(customerContextId);
                                if (authenticationState === 'succeeded') {
                                    fullScreenLoader.startLoader();
                                    this.setShippingAddress(profileData);
                                    fastlane.memberAuthenticated(true);
                                    fastlane.profileData = profileData;
                                }
                                return;
                            }
                            fastlane.memberAuthenticated(false);
                            resetShippingAddressAction();
                        } catch (error) {
                            fullScreenLoader.stopLoader();
                            console.error("Error:", error);
                        }
                    },

                    /**
                     * Set Fastlane shipping address to quote.
                     *
                     * @param {{}} profileData
                     * @return {void}
                     */
                    setShippingAddress: function (profileData) {
                        if (quote.isVirtual()) {
                            return;
                        }
                        const shippingAddress = profileData.shippingAddress || null;
                        if (!shippingAddress) {
                            resetShippingAddressAction();
                            return;
                        }
                        setQuoteShippingAddressAction(shippingAddress);
                    }
                }
            );
        };
    }
);
