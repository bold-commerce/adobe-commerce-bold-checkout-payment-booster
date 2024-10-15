define([
    'mage/utils/wrapper',
    'jquery'
], function (wrapper, $) {
    'use strict';

    let mixin = {
        handleHash: function (originalFn) {
            var hashString = window.location.hash.replace('#', '');
            if (hashString.indexOf('place_order') > -1) { 
                window.checkoutConfig.bold.scaRedirect = true;
                window.location.hash = '#payment'; 
            }
            return originalFn();
        }
    };

    return function (target) {
        return wrapper.extend(target, mixin);
    };
});