let config = {
    config: {
        mixins: {
            'Magento_Checkout/js/view/form/element/email': {
                'Bold_CheckoutPaymentBooster/js/view/form/element/email/fastlane-mixin': true
            },
            'Magento_Checkout/js/model/step-navigator': {
                'Bold_CheckoutPaymentBooster/js/model/step-navigator-mixin': true
            },
            'Swissup_Firecheckout/js/firecheckout': {
                'Bold_CheckoutPaymentBooster/js/view/swissup-firecheckout' : true
            }
        },
    },
    paths: {
        bold_braintree_client: 'https://js.braintreegateway.com/web/3.106.0/js/client.min',
        bold_braintree_data_collector: 'https://js.braintreegateway.com/web/3.106.0/js/data-collector.min',
        bold_braintree_google_payment: 'https://js.braintreegateway.com/web/3.106.0/js/google-payment.min',
        bold_braintree_paypal_checkout: 'https://js.braintreegateway.com/web/3.106.0/js/paypal-checkout.min',
        bold_google_pay: 'https://pay.google.com/gp/p/js/pay',
        bold_apple_pay: 'https://js.braintreegateway.com/web/3.106.0/js/apple-pay.min',
        bold_braintree_fastlane_client: 'https://js.braintreegateway.com/web/3.107.1/js/client.min',
        bold_braintree_fastlane: 'https://js.braintreegateway.com/web/3.107.1/js/fastlane',
        bold_braintree_fastlane_data_collector: 'https://js.braintreegateway.com/web/3.107.1/js/data-collector.min',
        bold_braintree_fastlane_hosted_fields: 'https://js.braintreegateway.com/web/3.107.1/js/hosted-fields.min',
        bold_ppcp_fastlane_client: 'https://js.braintreegateway.com/web/3.107.1/js/client.min',
        bold_ppcp_fastlane_hosted_fields: 'https://js.braintreegateway.com/web/3.107.1/js/hosted-fields.min',
        'fastlane/axo': 'https://www.paypalobjects.com/connect-boba/axo',
        'fastlane/axo.min': 'https://www.paypalobjects.com/connect-boba/axo.min'
    },
    shim: {
        'bold_braintree_client': {
            exports: 'braintree.client'
        },
        'bold_braintree_data_collector': {
            deps: ['bold_braintree_client'],
            exports: 'braintree.dataCollector'
        },
        'bold_braintree_data_google_payment': {
            deps: ['bold_braintree_client'],
            exports: 'braintree.googlePayment'
        },
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
