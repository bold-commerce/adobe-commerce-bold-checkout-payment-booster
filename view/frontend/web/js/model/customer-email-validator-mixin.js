define([
    'mage/utils/wrapper'
], function (wrapper) {
    'use strict';
    return function (targetModule) {
        targetModule.validate = wrapper.wrap(targetModule.validate, function (originalFunction) {

            if (window.location.href.includes('#shipping')) {
                return true;
            }

            return originalFunction();
        });

        return targetModule;
    };
});