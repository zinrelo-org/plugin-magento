define([
    'jquery',
    'underscore',
    'mage/utils/wrapper',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/cart/cache',
    'Magento_Checkout/js/action/select-shipping-method',
    'mage/cookies'
],function ($, _, wrapper, checkoutData, cartCache, selectShippingMethodAction) {
    'use strict';

    return function (checkoutDataResolver) {
        var config = window.checkoutConfig;

        /**
         * Select zinrelo shipping method when free shipping rule Applied
         */
        var resolveShippingRates = wrapper.wrap(
            checkoutDataResolver.resolveShippingRates,
            function (originalResolveShippingRates, ratesData) {
                let method = this.getMethod('shipping', ratesData);
                if (!_.isUndefined(method)) {
                    let isZinreloShippingSelected = ($.mage.cookies.get('zinrelo_shipping_selected'));
                    if (!isZinreloShippingSelected) {
                        /*get zinrelo free shipping cart-data rates and make default selected */
                        cartCache.clear('cart-data');
                        selectShippingMethodAction(method);
                        checkoutData.setSelectedShippingRate('zinrelorate_zinrelorate');
                        $.mage.cookies.set('zinrelo_shipping_selected', true, {lifetime: 86400});
                    }
                }
                return originalResolveShippingRates(ratesData);
            }
        );

        return _.extend(checkoutDataResolver, {
            resolveShippingRates: resolveShippingRates,

            /**
             * Return a selectable method
             *
             * @param  {String} type
             * @param  {Array} availableMethods
             * @return {Object|undefined}
             */
            getMethod: function (type, availableMethods) {
                self = this;
                var matchedMethod;
                matchedMethod = availableMethods.find(function (method) {
                    return self.getMethodCode(method, type) === 'zinrelorate_zinrelorate';
                });

                return matchedMethod;
            },

            /**
             * Get method code
             *
             * @param  {String} type
             * @return {String}
             */
            getMethodCode: function (method, type) {
                return type === 'shipping' ? method.carrier_code + '_' + method.method_code : method.method;
            }
        });
    };
});
