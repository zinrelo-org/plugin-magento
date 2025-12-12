define([
    'jquery',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils',
    'Magento_Customer/js/customer-data'
], function ($, quote, priceUtils, customerData) {
    'use strict';

    function runZinrelo() {
        const cartItems = window.checkoutConfig.quoteItemData;
        const zinreloItems = [];

        cartItems.forEach(function (item) {
            zinreloItems.push({
                product_id: item.product_id,
                quantity: item.qty,
                price: item.price,
                category: ''
            });
        });

        if (zinreloItems.length && typeof zrl_mi !== 'undefined' && typeof zrl_mi.get_potential_points === 'function') {
            zrl_mi.get_potential_points_success_handler = function () {
                const element = document.getElementById("zinrelo_cart_total_points");
                if (element && typeof potential_points !== 'undefined') {
                    element.innerText = potential_points;
                }
            };
            zrl_mi.get_potential_points(zinreloItems);
        } else {
            console.warn('Zinrelo not available or cart is empty.');
        }
    }

    return function () {
        $(document).ready(function () {
            runZinrelo();
            const cart = customerData.get('cart');
            cart.subscribe(function () {
                setTimeout(runZinrelo, 500);
            });
        });
    };
});
