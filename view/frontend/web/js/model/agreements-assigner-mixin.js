define([
    'jquery'
], function ($) {
    'use strict';

    return function (originalAgreementsAssigner) {
        return function (paymentData) {

            var agreementsConfig = window.checkoutConfig.checkoutAgreements;

            if (!agreementsConfig.isEnabled) {
                return;
            }

            var agreementForm = $('.payment-method._active div[data-role=checkout-agreements] input, ' +
                    'input[data-gdpr-checkbox-code], ' +
                    '.checkout-agreement input[type="checkbox"]'),
                agreementData,
                agreementIds;

            if(!agreementForm.length) {
                return;
            }

            agreementData = agreementForm.serializeArray();
            agreementIds = [];

            agreementData.forEach(function (item) {
                agreementIds.push(item.value);
            });

            if (paymentData['extension_attributes'] === undefined) {
                paymentData['extension_attributes'] = {};
            }

            paymentData['extension_attributes']['agreement_ids'] = agreementIds;
        };
    };
});
