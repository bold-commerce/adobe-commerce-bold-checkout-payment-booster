let config = {
    config: {
        mixins: {
            'Magento_Checkout/js/view/form/element/email': {
                'Bold_CheckoutPaymentBooster/js/view/form/element/email/fastlane-mixin': true
            },
            'Magento_Checkout/js/model/step-navigator': {
                'Bold_CheckoutPaymentBooster/js/model/step-navigator-mixin': true
            },
        },
    },
    paths: {
        bold_braintree_fastlane_client: 'https://js.braintreegateway.com/web/3.107.1/js/client.min',
        bold_braintree_fastlane: 'https://js.braintreegateway.com/web/3.107.1/js/fastlane',
        bold_braintree_fastlane_data_collector: 'https://js.braintreegateway.com/web/3.107.1/js/data-collector.min',
        bold_braintree_fastlane_hosted_fields: 'https://js.braintreegateway.com/web/3.107.1/js/hosted-fields.min',
        'fastlane/axo': 'https://www.paypalobjects.com/connect-boba/axo',
        'fastlane/axo.min': 'https://www.paypalobjects.com/connect-boba/axo.min'
    },
    shim: {
        'bold_braintree_fastlane_client': {
            exports: 'braintree.fastlane_client'
        },
        'bold_braintree_fastlane': {
            deps: ['bold_braintree_fastlane_client'],
            exports: 'braintree.fastlane'
        },
        'bold_braintree_fastlane_data_collector': {
            deps: ['bold_braintree_fastlane_client'],
            exports: 'braintree.dataCollector'
        },
        'bold_braintree_fastlane_hosted_fields': {
            deps: ['bold_braintree_fastlane_client'],
            exports: 'braintree.hostedFields'
        },
        'bold_paypal_fastlane_client': {
            exports: 'paypal.fastlane_client'
        },
        'bold_paypal_fastlane_hosted_fields': {
            deps: ['bold_paypal_fastlane_client'],
            exports: 'braintree.hostedFields'
        }
    }
};
