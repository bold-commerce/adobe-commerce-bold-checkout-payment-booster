let config = {
    config: {
        mixins: {
            'Magento_Checkout/js/view/form/element/email': {
                'Bold_CheckoutPaymentBooster/js/view/form/element/email/fastlane-mixin': true,
                'Bold_CheckoutPaymentBooster/js/view/form/element/email/paypal-insights-mixin': true
            },
            'Magento_Checkout/js/view/payment/default': {
                'Bold_CheckoutPaymentBooster/js/view/payment/paypal-insights-mixin': true
            }
        },
    },
    paths: {
        bold_braintree_fastlane_client: 'https://js.braintreegateway.com/web/3.101.0-fastlane-beta.7.2/js/client.min',
        bold_braintree_fastlane: 'https://js.braintreegateway.com/web/3.101.0-fastlane-beta.7.2/js/fastlane.min',
        bold_braintree_fastlane_data_collector: 'https://js.braintreegateway.com/web/3.101.0-fastlane-beta.7.2/js/data-collector.min',
        bold_braintree_fastlane_hosted_fields: 'https://js.braintreegateway.com/web/3.101.0-fastlane-beta.7.2/js/hosted-fields.min',
        bold_paypal_fastlane_client: 'https://js.braintreegateway.com/web/3.97.3-connect-alpha.6.1/js/client.min',
        bold_paypal_fastlane_hosted_fields: 'https://js.braintreegateway.com/web/3.97.3-connect-alpha.6.1/js/hosted-fields.min',
        bold_paypal_insights: 'https://www.paypalobjects.com/insights/v1/paypal-insights.min',
        bold_paypal_insights_sandbox: 'https://www.paypalobjects.com/insights/v1/paypal-insights.sandbox.min'
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
        },
        'bold_paypal_insights': {
            exports: 'paypalInsight'
        },
        'bold_paypal_insights_sandbox': {
            exports: 'paypalInsight'
        }
    }
};
