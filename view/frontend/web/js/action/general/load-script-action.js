define(
    [],
    function () {
        'use strict';
        /**
         * Load given script via require js.
         *
         * @param {string} type
         * @param {string} variable
         * @return {Promise<unknown>}
         */
        return function (type, variable = null) {
            return new Promise((resolve, reject) => {
                require([type], (src) => {
                    if (!variable) {
                        resolve(src);
                        return;
                    }
                    const variableParts = variable.split('.');
                    let current = window;
                    for (let i = 0; i < variableParts.length; i++) {
                        if (!current[variableParts[i]]) {
                            current[variableParts[i]] = {};
                        }
                        if (i === variableParts.length - 1) {
                            current[variableParts[i]] = src;
                        }
                        current = current[variableParts[i]];
                    }
                    resolve(src);
                }, reject);
            });
        };
    }
);
