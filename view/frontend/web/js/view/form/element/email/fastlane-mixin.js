define(
    [
        'Bold_CheckoutPaymentBooster/js/model/fastlane',
        'Bold_CheckoutPaymentBooster/js/view/shipping-address/list',
        'Magento_Customer/js/model/address-list',
        'Magento_Checkout/js/model/new-customer-address',
        'Magento_Checkout/js/action/create-shipping-address',
        'uiRegistry',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/action/select-shipping-address',
        'checkoutData',
        'Bold_CheckoutPaymentBooster/js/action/set-quote-shipping-address',
        'Bold_CheckoutPaymentBooster/js/action/reset-shipping-address'
    ], function (
        fastlane,
        addressList,
        customerAddressList,
        Address,
        createShippingAddress,
        registry,
        fullScreenLoader,
        selectShippingAddressAction,
        checkoutData,
        setQuoteShippingAddressAction,
        resetShippingAddressAction
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
                        if (!fastlane.isEnabled()) {
                            return;
                        }
                        this.template = 'Bold_CheckoutPaymentBooster/form/element/email';
                        fastlane.getFastlaneInstance().then((fastlaneInstance) => {
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
                        if (!fastlane.isEnabled() || !this.email()) {
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
                        fullScreenLoader.startLoader();
                        const fastlaneInstance = await fastlane.getFastlaneInstance();
                        if (!fastlaneInstance) {
                            return;
                        }
                        const identity = fastlaneInstance.identity;
                        try {
                            const {customerContextId} = await identity.lookupCustomerByEmail(this.email());
                            fullScreenLoader.stopLoader();
                            if (customerContextId) {
                                const {
                                    authenticationState,
                                    profileData
                                } = await identity.triggerAuthenticationFlow(customerContextId);
                                if (authenticationState === 'succeeded') {
                                    window.checkoutConfig.bold.fastlane.memberAuthenticated = true;
                                    fullScreenLoader.startLoader();
                                    this.setShippingAddress(profileData);
                                }
                                return;
                            }
                        } catch (error) {
                            fullScreenLoader.stopLoader();
                            console.error("Error:", error);
                        }
                        resetShippingAddressAction();
                    },

                    /**
                     * Set Fastlane shipping address to quote.
                     *
                     * @param {{}} profileData
                     * @return {void}
                     */
                    setShippingAddress: function (profileData) {
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